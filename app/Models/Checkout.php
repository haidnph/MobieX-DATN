<?php

require_once __DIR__ . '/../Core/Database.php';

final class Checkout
{
    private static function variantQuery(string $where = ''): string
    {
        return "SELECT v.id AS id,v.sku,p.name,COALESCE((SELECT MIN(fi.sale_price) FROM flash_sale_items fi JOIN flash_sales fs ON fs.id=fi.flash_sale_id WHERE fi.variant_id=v.id AND fs.status='active' AND fs.start_at<=NOW() AND fs.end_at>NOW() AND (fi.quantity_limit IS NULL OR fi.quantity_sold<fi.quantity_limit) AND fi.sale_price>0 AND fi.sale_price<v.price),v.price) AS price,COALESCE(i.available_quantity,0) AS stock_quantity,p.id AS product_id,
                       GROUP_CONCAT(CONCAT(a.name, ': ', av.value) ORDER BY a.name SEPARATOR ' · ') AS variant_name
                FROM product_variants v JOIN products p ON p.id=v.product_id
                LEFT JOIN inventories i ON i.variant_id=v.id
                LEFT JOIN variant_attribute_values vav ON vav.variant_id=v.id
                LEFT JOIN attribute_values av ON av.id=vav.attribute_value_id
                LEFT JOIN attributes a ON a.id=av.attribute_id
                WHERE p.status='active' AND v.status='active' $where
                GROUP BY v.id,p.id,p.name,v.sku,v.price,i.available_quantity";
    }

    public static function availableProducts(): array
    {
        return Database::connection()->query(self::variantQuery('AND COALESCE(i.available_quantity,0)>0') . ' ORDER BY p.name,v.sku LIMIT 200')->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function cartProducts(array $cart): array
    {
        $ids = array_values(array_filter(array_map('intval', array_keys($cart)), static fn(int $id): bool => $id > 0));
        if (!$ids) return [];
        $marks = implode(',', array_fill(0, count($ids), '?'));
        $q = Database::connection()->prepare(self::variantQuery("AND v.id IN ($marks)") . ' ORDER BY p.name,v.sku');
        $q->execute($ids); $items = $q->fetchAll(PDO::FETCH_ASSOC);
        foreach ($items as &$item) $item['quantity'] = (int)($cart[$item['id']] ?? 0);
        return $items;
    }

    public static function assertAvailableQuantity(int $variantId, int $quantity): void
    {
        $q=Database::connection()->prepare("SELECT i.available_quantity FROM product_variants v JOIN products p ON p.id=v.product_id JOIN inventories i ON i.variant_id=v.id WHERE v.id=? AND v.status='active' AND p.status='active'");
        $q->execute([$variantId]); $stock=$q->fetchColumn();
        if ($stock === false || $quantity > (int)$stock) throw new RuntimeException('Tồn kho không đủ cho số lượng yêu cầu.');
    }

    public static function shippingMethods(): array
    {
        return Database::connection()->query('SELECT id,code,name,fee,estimated_days FROM shipping_methods WHERE is_active=1 ORDER BY fee')->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function savedAddresses(int $userId): array
    {
        $q=Database::connection()->prepare('SELECT id,label,full_name,phone,city,district,ward,address_line,is_default FROM user_addresses WHERE user_id=? ORDER BY is_default DESC,id DESC');$q->execute([$userId]);return $q->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function coupon(string $code, float $subtotal): ?array
    {
        if ($code === '') return null;
        $q=Database::connection()->prepare('SELECT id,code,discount_type,discount_value,min_order_amount,max_discount FROM coupons WHERE UPPER(code)=UPPER(?) AND is_active=1 AND (start_at IS NULL OR start_at<=NOW()) AND (end_at IS NULL OR end_at>=NOW()) AND (usage_limit IS NULL OR used<usage_limit) LIMIT 1');
        $q->execute([$code]); $row=$q->fetch(PDO::FETCH_ASSOC);
        return $row && $subtotal >= (float)$row['min_order_amount'] ? $row : null;
    }

    public static function couponDiscount(string $code, float $subtotal): float
    {
        $c=self::coupon($code,$subtotal); if (!$c) return 0.0;
        $discount=$c['discount_type']==='percent' ? $subtotal*(float)$c['discount_value']/100 : (float)$c['discount_value'];
        if ($c['max_discount']!==null) $discount=min($discount,(float)$c['max_discount']);
        return round(min($subtotal,$discount),2);
    }

    public static function placeCart(int $userId, array $cart, array $input): string
    {
        if ($userId<1 || !$cart) throw new InvalidArgumentException('Giỏ hàng đang trống.');
        $name=trim((string)($input['name']??'')); $phone=trim((string)($input['phone']??'')); $address=trim((string)($input['address']??''));
        if ($name==='' || mb_strlen($name)>255 || !preg_match('/^[0-9+ .()-]{9,20}$/',$phone) || $address==='' || mb_strlen($address)>255) throw new InvalidArgumentException('Vui lòng nhập tên, số điện thoại và địa chỉ nhận hàng hợp lệ.');
        $db=Database::connection(); $db->beginTransaction();
        try {
            $items=[]; $subtotal=0.0;
            foreach($cart as $variantId=>$qty) {
                $variantId=(int)$variantId; $qty=(int)$qty;
                if($variantId<1 || $qty<1 || $qty>99) throw new InvalidArgumentException('Số lượng trong giỏ không hợp lệ.');
                $q=$db->prepare("SELECT v.id,v.product_id,v.sku,COALESCE((SELECT MIN(fi.sale_price) FROM flash_sale_items fi JOIN flash_sales fs ON fs.id=fi.flash_sale_id WHERE fi.variant_id=v.id AND fs.status='active' AND fs.start_at<=NOW() AND fs.end_at>NOW() AND (fi.quantity_limit IS NULL OR fi.quantity_sold<fi.quantity_limit) AND fi.sale_price>0 AND fi.sale_price<v.price),v.price) price,(SELECT fi.id FROM flash_sale_items fi JOIN flash_sales fs ON fs.id=fi.flash_sale_id WHERE fi.variant_id=v.id AND fs.status='active' AND fs.start_at<=NOW() AND fs.end_at>NOW() AND (fi.quantity_limit IS NULL OR fi.quantity_sold<fi.quantity_limit) AND fi.sale_price>0 AND fi.sale_price<v.price ORDER BY fi.sale_price LIMIT 1) flash_sale_item_id,p.name,COALESCE(i.available_quantity,0) stock FROM product_variants v JOIN products p ON p.id=v.product_id JOIN inventories i ON i.variant_id=v.id WHERE v.id=? AND v.status='active' AND p.status='active' FOR UPDATE");
                $q->execute([$variantId]); $v=$q->fetch(PDO::FETCH_ASSOC);
                if(!$v || (int)$v['stock']<$qty) throw new RuntimeException('Tồn kho đã thay đổi. Vui lòng cập nhật giỏ hàng.');
                if($v['flash_sale_item_id']){$flash=$db->prepare('SELECT quantity_limit,quantity_sold FROM flash_sale_items WHERE id=? FOR UPDATE');$flash->execute([$v['flash_sale_item_id']]);$limit=$flash->fetch(PDO::FETCH_ASSOC);if(!$limit||($limit['quantity_limit']!==null&&(int)$limit['quantity_limit']-(int)$limit['quantity_sold']<$qty))throw new RuntimeException('Số lượng ưu đãi Flash Sale còn lại không đủ.');}
                $line=(float)$v['price']*$qty; $subtotal+=$line; $v['quantity']=$qty; $v['line_total']=$line; $items[]=$v;
            }
            $sm=$db->prepare('SELECT id,fee FROM shipping_methods WHERE code=? AND is_active=1 LIMIT 1'); $sm->execute([(string)($input['shipping']??'')]); $shipping=$sm->fetch(PDO::FETCH_ASSOC);
            if(!$shipping) throw new InvalidArgumentException('Vui lòng chọn phương thức vận chuyển hợp lệ.');
            $method=(string)($input['payment']??'COD'); if(!in_array($method,['COD','VNPAY','MOMO'],true)) throw new InvalidArgumentException('Phương thức thanh toán không hợp lệ.');
            $coupon=self::coupon((string)($input['coupon']??''),$subtotal);
            if(!empty($input['coupon']) && !$coupon) throw new InvalidArgumentException('Mã giảm giá không hợp lệ hoặc chưa đạt giá trị đơn tối thiểu.');
            $discount=0.0;
            if($coupon){$discount=$coupon['discount_type']==='percent'?$subtotal*(float)$coupon['discount_value']/100:(float)$coupon['discount_value'];if($coupon['max_discount']!==null)$discount=min($discount,(float)$coupon['max_discount']);$discount=min($subtotal,$discount);}
            $fee=(float)$shipping['fee']; $total=max(0,$subtotal-$discount)+$fee; $code='MX-'.strtoupper(bin2hex(random_bytes(8)));
            $q=$db->prepare("INSERT INTO orders (user_id,coupon_id,shipping_method_id,order_code,subtotal,discount_amount,shipping_fee,total_amount,shipping_name,shipping_phone,shipping_address,shipping_city,shipping_district,shipping_ward,payment_method,order_status,payment_status) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,'PENDING','PENDING')");
            $q->execute([$userId,$coupon['id']??null,$shipping['id'],$code,$subtotal,$discount,$fee,$total,$name,$phone,$address,(string)($input['city']??''),(string)($input['district']??''),(string)($input['ward']??''),$method]); $orderId=(int)$db->lastInsertId();
            $add=$db->prepare('INSERT INTO order_items (order_id,product_variant_id,product_name,variant_name,sku,price,quantity,subtotal) VALUES (?,?,?,?,?,?,?,?)');
            $decrease=$db->prepare('UPDATE inventories SET quantity=quantity-? WHERE variant_id=? AND available_quantity>=?');
            foreach($items as $item){$add->execute([$orderId,$item['id'],$item['name'],$item['variant_name']??null,$item['sku'],$item['price'],$item['quantity'],$item['line_total']]);$decrease->execute([$item['quantity'],$item['id'],$item['quantity']]);if($decrease->rowCount()!==1)throw new RuntimeException('Không thể giữ tồn kho, vui lòng thử lại.');if($item['flash_sale_item_id']){$db->prepare('UPDATE flash_sale_items SET quantity_sold=quantity_sold+? WHERE id=?')->execute([$item['quantity'],$item['flash_sale_item_id']]);}}
            if($coupon){$use=$db->prepare('UPDATE coupons SET used=used+1 WHERE id=? AND (usage_limit IS NULL OR used<usage_limit)');$use->execute([$coupon['id']]);if($use->rowCount()!==1)throw new RuntimeException('Mã giảm giá vừa hết lượt sử dụng. Vui lòng thử lại.');}
            $pay=$db->prepare('INSERT INTO payments (order_id,transaction_code,method,amount,status) VALUES (?,?,?,?,\'PENDING\')');$pay->execute([$orderId,$code,$method,$total]);
            $db->commit(); return $code;
        }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
    }
}
