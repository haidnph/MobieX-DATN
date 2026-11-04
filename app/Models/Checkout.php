<?php
declare(strict_types=1);

require_once __DIR__ . '/../Core/Database.php';

final class Checkout
{
    public static function availableProducts(): array
    {
        return Database::connection()->query(
            'SELECT id, name, sku, price, stock_quantity FROM products WHERE is_active = 1 AND stock_quantity > 0 ORDER BY name LIMIT 100'
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function cartProducts(array $cart): array
    {
        $ids = array_values(array_filter(array_map('intval', array_keys($cart)), static fn(int $id): bool => $id > 0));
        if (!$ids) return [];
        $marks = implode(',', array_fill(0, count($ids), '?'));
        $q = Database::connection()->prepare("SELECT id,name,sku,price,stock_quantity FROM products WHERE is_active=1 AND id IN ($marks) ORDER BY name");
        $q->execute($ids);
        $items = $q->fetchAll(PDO::FETCH_ASSOC);
        foreach ($items as &$item) $item['quantity'] = (int)($cart[$item['id']] ?? 0);
        return $items;
    }

    public static function assertAvailableQuantity(int $productId, int $quantity): void
    {
        $q = Database::connection()->prepare('SELECT stock_quantity,is_active FROM products WHERE id=?');
        $q->execute([$productId]); $row = $q->fetch(PDO::FETCH_ASSOC);
        if (!$row || (int)$row['is_active'] !== 1 || $quantity > (int)$row['stock_quantity']) {
            throw new RuntimeException('Tồn kho không đủ cho số lượng yêu cầu.');
        }
    }

    public static function couponDiscount(string $code, float $subtotal): float
    {
        if ($code === '') return 0;
        try {
            $q = Database::connection()->prepare("SELECT discount_type,discount_value,min_order_amount,max_discount FROM datn_coupons WHERE UPPER(code)=UPPER(?) AND is_active=1 AND (start_at IS NULL OR start_at<=NOW()) AND (end_at IS NULL OR end_at>=NOW()) AND (usage_limit IS NULL OR used<usage_limit) LIMIT 1");
            $q->execute([$code]); $coupon = $q->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) { return 0; }
        if (!$coupon || $subtotal < (float)$coupon['min_order_amount']) return 0;
        $discount = $coupon['discount_type'] === 'percent' ? $subtotal * (float)$coupon['discount_value'] / 100 : (float)$coupon['discount_value'];
        if ($coupon['max_discount'] !== null) $discount = min($discount, (float)$coupon['max_discount']);
        return round(min($subtotal, $discount), 2);
    }

    public static function placeCart(int $userId, array $cart, array $shipping): string
    {
        if ($userId < 1 || !$cart) throw new InvalidArgumentException('Giỏ hàng đang trống.');
        foreach (['name'=>120,'phone'=>20,'address'=>255] as $key=>$max) {
            if (empty($shipping[$key]) || mb_strlen($shipping[$key]) > $max) throw new InvalidArgumentException('Vui lòng nhập đầy đủ thông tin giao hàng hợp lệ.');
        }
        if (!preg_match('/^[0-9+ .-]{9,16}$/', $shipping['phone'])) throw new InvalidArgumentException('Số điện thoại không hợp lệ.');
        $shippingFees = ['standard'=>30000.0,'express'=>60000.0,'pickup'=>0.0];
        if (!isset($shippingFees[$shipping['shipping']]) || !in_array($shipping['payment'], ['COD','VNPAY','MOMO'], true)) throw new InvalidArgumentException('Phương thức giao hàng hoặc thanh toán không hợp lệ.');
        $db=Database::connection(); $db->beginTransaction();
        try {
            $subtotal=0.0; $rows=[];
            foreach ($cart as $id=>$quantity) {
                $id=(int)$id; $quantity=(int)$quantity;
                if ($id<1 || $quantity<1 || $quantity>99) throw new InvalidArgumentException('Số lượng trong giỏ không hợp lệ.');
                $q=$db->prepare('SELECT id,name,price,stock_quantity,is_active FROM products WHERE id=? FOR UPDATE'); $q->execute([$id]); $p=$q->fetch(PDO::FETCH_ASSOC);
                if (!$p || (int)$p['is_active']!==1 || (int)$p['stock_quantity']<$quantity) throw new RuntimeException('Tồn kho đã thay đổi. Vui lòng cập nhật lại giỏ hàng.');
                $subtotal+=(float)$p['price']*$quantity; $rows[]=[$p,$quantity];
            }
            $discount=self::couponDiscount((string)$shipping['coupon'],$subtotal);
            if ($shipping['coupon'] !== '' && $discount <= 0) throw new InvalidArgumentException('Mã giảm giá không hợp lệ hoặc chưa đạt giá trị đơn tối thiểu.');
            $fee=$shippingFees[$shipping['shipping']]; $total=max(0,$subtotal-$discount)+$fee;
            $code='MX-'.strtoupper(bin2hex(random_bytes(8)));
            $q=$db->prepare("INSERT INTO orders (order_code,user_id,status,total_amount,shipping_name,shipping_phone,shipping_address,shipping_method,payment_method,subtotal,discount_amount,shipping_fee) VALUES (?,?,'pending',?,?,?,?,?,?,?,?,?)");
            $q->execute([$code,$userId,number_format($total,2,'.',''),$shipping['name'],$shipping['phone'],$shipping['address'],$shipping['shipping'],$shipping['payment'],number_format($subtotal,2,'.',''),number_format($discount,2,'.',''),number_format($fee,2,'.','')]);
            $orderId=(int)$db->lastInsertId(); $insert=$db->prepare('INSERT INTO order_items (order_id,product_id,quantity,unit_price) VALUES (?,?,?,?)'); $update=$db->prepare('UPDATE products SET stock_quantity=stock_quantity-? WHERE id=?');
            foreach($rows as [$p,$qty]) { $insert->execute([$orderId,$p['id'],$qty,$p['price']]); $update->execute([$qty,$p['id']]); }
            $db->commit(); return $code;
        } catch(Throwable $e) { if($db->inTransaction()) $db->rollBack(); throw $e; }
    }

    /** Create a pending COD order atomically, reserving stock immediately. */
    public static function place(int $userId, int $productId, int $quantity): string
    {
        if ($userId < 1 || $productId < 1 || $quantity < 1 || $quantity > 10) {
            throw new InvalidArgumentException('Thông tin sản phẩm hoặc số lượng không hợp lệ.');
        }
        $db = Database::connection();
        $db->beginTransaction();
        try {
            $q = $db->prepare('SELECT id, price, stock_quantity, is_active FROM products WHERE id = ? FOR UPDATE');
            $q->execute([$productId]);
            $product = $q->fetch(PDO::FETCH_ASSOC);
            if (!$product || (int)$product['is_active'] !== 1 || (int)$product['stock_quantity'] < $quantity) {
                throw new RuntimeException('Sản phẩm đã hết hàng hoặc số lượng không đủ.');
            }
            // Never trust a price sent by the browser.
            $unitPrice = (string)$product['price'];
            if ((float)$unitPrice <= 0) {
                throw new RuntimeException('Giá sản phẩm chưa hợp lệ.');
            }
            $total = number_format(round((float)$unitPrice * $quantity, 2), 2, '.', '');
            $code = 'MX-' . strtoupper(bin2hex(random_bytes(8)));
            $q = $db->prepare("INSERT INTO orders (order_code, user_id, status, total_amount) VALUES (?, ?, 'pending', ?)");
            $q->execute([$code, $userId, $total]);
            $orderId = (int)$db->lastInsertId();
            $q = $db->prepare('INSERT INTO order_items (order_id, product_id, quantity, unit_price) VALUES (?, ?, ?, ?)');
            $q->execute([$orderId, $productId, $quantity, $unitPrice]);
            $q = $db->prepare('UPDATE products SET stock_quantity = stock_quantity - ? WHERE id = ?');
            $q->execute([$quantity, $productId]);
            $db->commit();
            return $code;
        } catch (Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            throw $e;
        }
    }
}
