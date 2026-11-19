                if ($coupon->discount_type === 'percent') {
                    $discountAmount = round(($subtotal * (float) $coupon->discount_value) / 100, 2);
                    if ($coupon->max_discount !== null && $discountAmount > (float) $coupon->max_discount) {
                        $discountAmount = (float) $coupon->max_discount;
                    }
                } elseif ($coupon->discount_type === 'fixed') {
                    $discountAmount = min((float) $coupon->discount_value, $subtotal);
                }

                $coupon->reserved_count += 1;
                $coupon->save();
            }

            $totalAmount = max(0.0, round($subtotal - $discountAmount + $shippingFee, 2));

            $order = Order::create([
                'user_id' => $userId,
                'order_code' => 'MX-'.Str::upper(Str::random(12)),
                'coupon_id' => $coupon?->id,
                'coupon_code' => $coupon?->code,
                'shipping_method_id' => $shippingMethod->id,
                'shipping_method_code' => $shippingMethod->code,
                'shipping_method_name' => $shippingMethod->name,
                'subtotal' => $subtotal,
                'discount_amount' => $discountAmount,
                'shipping_fee' => $shippingFee,
                'total_amount' => $totalAmount,
                'currency_code' => 'VND',
                'shipping_name' => $validated['shipping_name'],
                'shipping_phone' => $validated['shipping_phone'],
                'shipping_address_line' => $addressLine,
                'shipping_province_code' => $validated['shipping_province_code'] ?? 'VN-HN',
                'shipping_province_name' => $validated['shipping_province_name'] ?? $validated['shipping_city'] ?? 'Hà Nội',
                'shipping_ward_code' => $validated['shipping_ward_code'] ?? '001',
                'shipping_ward_name' => $validated['shipping_ward_name'] ?? $validated['shipping_ward'] ?? 'Dịch Vọng Hậu',
                'payment_method' => strtolower($validated['payment_method']),
                'payment_status' => 'pending',
                'status' => 'pending',
                'customer_note' => $validated['customer_note'] ?? null,
            ]);

            if ($coupon) {
                CouponRedemption::create([
                    'coupon_id' => $coupon->id,
                    'order_id' => $order->id,
                    'user_id' => $userId,
                    'discount_amount' => $discountAmount,
                    'status' => 'reserved',
                ]);
            }

            foreach ($preparedItems as $item) {
                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $item['product']->id,
                    'product_variant_id' => $item['variant']->id,
                    'product_name' => $item['product_name'],
                    'variant_name' => $item['variant_name'],
                    'sku' => $item['sku'],
                    'image_path' => $item['variant']->image_path ?? $item['product']->thumbnail_url,
                    'price' => $item['price'],
                    'cost_price_snapshot' => $item['variant']->cost_price ?? null,
                    'quantity' => $item['quantity'],
                ]);

                // Stock Reservation: Keep on_hand_quantity intact, increase reserved_quantity
                $inventory = $item['inventory'];
                $inventory->reserved_quantity += $item['quantity'];
                $inventory->save();

                InventoryTransaction::create([
                    'product_variant_id' => $item['variant']->id,
                    'user_id' => $userId,
                    'type' => 'reserve',
                    'quantity_delta' => 0,
                    'reserved_delta' => $item['quantity'],
                    'unit_cost' => $item['variant']->cost_price ?? null,
                    'reference_type' => 'order',
                    'reference_id' => $order->id,
                    'idempotency_key' => "order_{$order->id}_var_{$item['variant']->id}_reserve",
                    'note' => 'Giữ chỗ cho đơn hàng '.$order->order_code,
                    'created_at' => now(),
                ]);
            }

            OrderStatusHistory::create([
                'order_id' => $order->id,
                'user_id' => $userId,
                'from_status' => null,
                'status' => 'pending',
                'note' => 'Đơn hàng được tạo thành công.',
                'created_at' => now(),
            ]);

            $cart->items()->delete();

            return $order;
        });

        $order->load(['items.variant.product', 'statusHistories']);

        return response()->json([
            'message' => 'Đặt hàng thành công.',
            'data' => $order,
        ], 201);
    }
}
