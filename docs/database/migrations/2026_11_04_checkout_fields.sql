-- Run once on databases created before multi-item checkout was added.
ALTER TABLE `orders`
  ADD COLUMN `subtotal` DECIMAL(15,2) NOT NULL DEFAULT 0,
  ADD COLUMN `discount_amount` DECIMAL(15,2) NOT NULL DEFAULT 0,
  ADD COLUMN `shipping_fee` DECIMAL(15,2) NOT NULL DEFAULT 0,
  ADD COLUMN `shipping_name` VARCHAR(120) NULL,
  ADD COLUMN `shipping_phone` VARCHAR(20) NULL,
  ADD COLUMN `shipping_address` VARCHAR(255) NULL,
  ADD COLUMN `shipping_method` VARCHAR(20) NOT NULL DEFAULT 'standard',
  ADD COLUMN `payment_method` ENUM('COD','VNPAY','MOMO') NOT NULL DEFAULT 'COD';
