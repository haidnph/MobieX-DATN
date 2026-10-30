-- ============================================================================
-- MOBILEX UNIFIED SCHEMA - 2026-10-29
-- ============================================================================
-- Target: mobilex_store (MySQL 8.0; UTF8MB4). No DROP/DELETE/UPDATE of user data.
-- SOURCE A: datn_mobilex_final.sql -> 29 preserved tables, prefixed datn_.
-- SOURCE B: MobileX Admin/Dashboard, reviews, Client photos, Banner, Flash Sale.
-- WHY PREFIX? The 2 sources implement INCOMPATIBLE models. e.g. datn_products
-- has variants, while live MobileX products has direct sku/price/stock_quantity.
-- Keeping both namespaces avoids name clashes and preserves future migration options.
-- This is ONE importable SQL file for both NEW DB and existing mobilex_store.
-- Warning: CREATE TABLE IF NOT EXISTS DOES NOT MODIFY existing incompatible tables.
-- Following diagnostics report compatibility gaps for existing schemas.
-- IMPORTANT: data between datn_* and live tables is NOT automatically synchronized.
-- Database import doesn't itself fix PHP schema mismatches, checkout or pricing logic.
-- Back up mobilex_store in phpMyAdmin before running any SQL.
-- ============================================================================
CREATE DATABASE IF NOT EXISTS `mobilex_store` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `mobilex_store`;
SET NAMES utf8mb4;

-- [01] AUTH / USERS / PASSWORD RESET / REMEMBER LOGIN (live PHP models)
CREATE TABLE IF NOT EXISTS `users` (
 `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 `name` VARCHAR(120) NOT NULL,
 `email` VARCHAR(254) NOT NULL UNIQUE,
 `password_hash` VARCHAR(255) NOT NULL,
 `role` ENUM('customer','admin') NOT NULL DEFAULT 'customer',
 `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `password_resets` (
 `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 `user_id` BIGINT UNSIGNED NOT NULL,
 `token_hash` CHAR(64) NOT NULL UNIQUE,
 `expires_at` DATETIME NOT NULL,
 `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 CONSTRAINT `mx_password_resets_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `user_remember_tokens` (
 `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 `user_id` BIGINT UNSIGNED NOT NULL,
 `selector` CHAR(24) NOT NULL UNIQUE,
 `token_hash` CHAR(64) NOT NULL,
 `expires_at` DATETIME NOT NULL,
 `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 CONSTRAINT `mx_remember_tokens_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- [02] PRODUCTS / INVENTORY (live Dashboard uses direct fields)
CREATE TABLE IF NOT EXISTS `products` (
 `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 `sku` VARCHAR(80) NOT NULL UNIQUE,
 `name` VARCHAR(255) NOT NULL,
 `price` DECIMAL(15,2) NOT NULL DEFAULT 0,
 `stock_quantity` INT UNSIGNED NOT NULL DEFAULT 0,
 `is_active` TINYINT(1) NOT NULL DEFAULT 1,
 `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 INDEX `mx_products_stock_idx` (`stock_quantity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- [03] ORDERS / ORDER ITEMS (live Dashboard and verified purchases)
CREATE TABLE IF NOT EXISTS `orders` (
 `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 `order_code` VARCHAR(50) NOT NULL UNIQUE,
 `user_id` BIGINT UNSIGNED NULL,
 `status` ENUM('pending','confirmed','shipping','completed','cancelled') NOT NULL DEFAULT 'pending',
 `total_amount` DECIMAL(15,2) NOT NULL DEFAULT 0,
 `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 `completed_at` DATETIME NULL,
 `cancelled_at` DATETIME NULL,
 INDEX `mx_order_status_idx` (`status`),
 INDEX `mx_order_completed_idx` (`completed_at`),
 CONSTRAINT `mx_orders_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `order_items` (
 `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 `order_id` BIGINT UNSIGNED NOT NULL,
 `product_id` BIGINT UNSIGNED NOT NULL,
 `quantity` INT UNSIGNED NOT NULL,
 `unit_price` DECIMAL(15,2) NOT NULL,
 CONSTRAINT `mx_order_items_order_fk` FOREIGN KEY (`order_id`) REFERENCES `orders`(`id`) ON DELETE CASCADE,
 CONSTRAINT `mx_order_items_product_fk` FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- [04] DASHBOARD / ANALYTICS: NO dedicated table. Reads orders/products/users.
-- [05] REVIEW MODERATION / ADMIN: pending -> approved or hidden
CREATE TABLE IF NOT EXISTS `product_reviews` (
 `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 `user_id` BIGINT UNSIGNED NOT NULL,
 `product_id` BIGINT UNSIGNED NOT NULL,
 `rating` TINYINT UNSIGNED NOT NULL,
 `content` TEXT NOT NULL,
 `status` ENUM('pending','approved','hidden') NOT NULL DEFAULT 'pending',
 `moderation_reason` VARCHAR(500) NULL,
 `moderated_by` BIGINT UNSIGNED NULL,
 `moderated_at` DATETIME NULL,
 `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 INDEX `mx_review_status_date_idx` (`status`,`created_at`),
 INDEX `mx_review_product_idx` (`product_id`,`status`),
 INDEX `mx_review_user_idx` (`user_id`),
 CONSTRAINT `mx_reviews_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
 CONSTRAINT `mx_reviews_product_fk` FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE,
 CONSTRAINT `mx_reviews_admin_fk` FOREIGN KEY (`moderated_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
 CONSTRAINT `mx_review_rating_chk` CHECK (`rating` BETWEEN 1 AND 5)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- [06] CLIENT PRODUCT REVIEW ATTACHMENTS
CREATE TABLE IF NOT EXISTS `product_review_images` (
 `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 `review_id` BIGINT UNSIGNED NOT NULL,
 `image_path` VARCHAR(300) NOT NULL,
 `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 INDEX `mx_review_img_idx` (`review_id`),
 CONSTRAINT `mx_review_img_fk` FOREIGN KEY (`review_id`) REFERENCES `product_reviews`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- [07] HOME PAGE SLIDER BANNERS / ADMIN toggle and ordering
CREATE TABLE IF NOT EXISTS `home_banners` (
 `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 `title` VARCHAR(180) NOT NULL,
 `image_path` VARCHAR(300) NOT NULL,
 `target_url` VARCHAR(500) NOT NULL DEFAULT '',
 `sort_order` INT NOT NULL DEFAULT 0,
 `is_active` TINYINT(1) NOT NULL DEFAULT 1,
 `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 INDEX `mx_home_banners_idx` (`is_active`,`sort_order`,`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- [08] FLASH SALE CAMPAIGNS / PRODUCTS / TIME WINDOW (live PHP Promotions)
CREATE TABLE IF NOT EXISTS `flash_sales` (
 `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 `name` VARCHAR(180) NOT NULL,
 `starts_at` DATETIME NOT NULL,
 `ends_at` DATETIME NOT NULL,
 `is_active` TINYINT(1) NOT NULL DEFAULT 1,
 `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 INDEX `mx_flash_window_idx` (`is_active`,`starts_at`,`ends_at`),
 CONSTRAINT `mx_flash_dates_chk` CHECK (`ends_at` > `starts_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `flash_sale_products` (
 `flash_sale_id` BIGINT UNSIGNED NOT NULL,
 `product_id` BIGINT UNSIGNED NOT NULL,
 `sale_price` DECIMAL(15,2) NOT NULL,
 PRIMARY KEY (`flash_sale_id`,`product_id`),
 CONSTRAINT `mx_flash_product_sale_fk` FOREIGN KEY (`flash_sale_id`) REFERENCES `flash_sales`(`id`) ON DELETE CASCADE,
 CONSTRAINT `mx_flash_product_product_fk` FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE,
 CONSTRAINT `mx_flash_price_chk` CHECK (`sale_price` > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- [09] SOURCE ORIGINAL datn_mobilex_final.sql: 29 TABLES, separate namespace
-- Legacy source schema kept intact, including its role seeds, indexes, foreign keys.
-- All source tables named datn_*, so they CANNOT collide with live users/products etc.
-- Do NOT JOIN datn_orders with live order_items without explicit migration mapping.
-- Source review status 'rejected' differs from live 'hidden'.
-- Source order status 'DELIVERED' differs from live 'completed'.
-- Source banner `banners.image_url` differs from live `home_banners.image_path`.
-- Source prices/stock live in `datn_product_variants`/`datn_inventories`.

-- [09.01] Original table attributes -> datn_attributes
CREATE TABLE IF NOT EXISTS `datn_attributes` (
 `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `code` varchar(100) NOT NULL,
  `type` varchar(50) NOT NULL DEFAULT 'select',
  `sort_order` int NOT NULL DEFAULT '0',
  `status` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 PRIMARY KEY (`id`),
 UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- [09.02] Original table banners -> datn_banners
CREATE TABLE IF NOT EXISTS `datn_banners` (
 `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `image_url` varchar(255) NOT NULL,
  `target_url` varchar(255) DEFAULT NULL,
  `position` varchar(100) NOT NULL DEFAULT 'home',
  `sort_order` int NOT NULL DEFAULT '0',
  `start_at` datetime DEFAULT NULL,
  `end_at` datetime DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- [09.03] Original table brands -> datn_brands
CREATE TABLE IF NOT EXISTS `datn_brands` (
 `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `logo` varchar(255) DEFAULT NULL,
  `description` text,
  `status` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 PRIMARY KEY (`id`),
 UNIQUE KEY `slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- [09.04] Original table categories -> datn_categories
CREATE TABLE IF NOT EXISTS `datn_categories` (
 `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `parent_id` bigint UNSIGNED DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `description` text,
  `image` varchar(255) DEFAULT NULL,
  `sort_order` int NOT NULL DEFAULT '0',
  `status` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 PRIMARY KEY (`id`),
 UNIQUE KEY `slug` (`slug`),
 KEY `idx_categories_parent` (`parent_id`),
 CONSTRAINT `datn_categories_ibfk_1` FOREIGN KEY (`parent_id`) REFERENCES `datn_categories` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- [09.05] Original table coupons -> datn_coupons
CREATE TABLE IF NOT EXISTS `datn_coupons` (
 `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `code` varchar(50) NOT NULL,
  `discount_type` enum('fixed','percent') NOT NULL,
  `discount_value` decimal(12,2) NOT NULL,
  `min_order_amount` decimal(12,2) NOT NULL DEFAULT '0.00',
  `max_discount` decimal(12,2) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `usage_limit` int UNSIGNED DEFAULT NULL,
  `used` int UNSIGNED NOT NULL DEFAULT '0',
  `start_at` datetime DEFAULT NULL,
  `end_at` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
 PRIMARY KEY (`id`),
 UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- [09.06] Original table permissions -> datn_permissions
CREATE TABLE IF NOT EXISTS `datn_permissions` (
 `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `slug` varchar(100) NOT NULL,
  `description` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 PRIMARY KEY (`id`),
 UNIQUE KEY `slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- [09.07] Original table roles -> datn_roles
CREATE TABLE IF NOT EXISTS `datn_roles` (
 `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `slug` varchar(100) NOT NULL,
  `description` text,
  `is_system` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 PRIMARY KEY (`id`),
 UNIQUE KEY `name` (`name`),
 UNIQUE KEY `slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- [09.08] Original table shipping_methods -> datn_shipping_methods
CREATE TABLE IF NOT EXISTS `datn_shipping_methods` (
 `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `code` varchar(50) NOT NULL,
  `description` text,
  `fee` decimal(12,2) NOT NULL DEFAULT '0.00',
  `estimated_days` varchar(50) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 PRIMARY KEY (`id`),
 UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- [09.09] Original table users -> datn_users
CREATE TABLE IF NOT EXISTS `datn_users` (
 `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `remember_token` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 PRIMARY KEY (`id`),
 UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- [09.10] Original table attribute_values -> datn_attribute_values
CREATE TABLE IF NOT EXISTS `datn_attribute_values` (
 `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `attribute_id` bigint UNSIGNED NOT NULL,
  `value` varchar(255) NOT NULL,
  `color_code` varchar(20) DEFAULT NULL,
  `sort_order` int NOT NULL DEFAULT '0',
  `status` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 PRIMARY KEY (`id`),
 UNIQUE KEY `uq_attribute_value` (`attribute_id`,`value`),
 CONSTRAINT `datn_attribute_values_ibfk_1` FOREIGN KEY (`attribute_id`) REFERENCES `datn_attributes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- [09.11] Original table carts -> datn_carts
CREATE TABLE IF NOT EXISTS `datn_carts` (
 `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` bigint UNSIGNED DEFAULT NULL,
  `session_id` varchar(128) DEFAULT NULL,
  `status` enum('active','converted','abandoned') NOT NULL DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 PRIMARY KEY (`id`),
 KEY `user_id` (`user_id`),
 CONSTRAINT `datn_carts_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `datn_users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- [09.12] Original table flash_sales -> datn_flash_sales
CREATE TABLE IF NOT EXISTS `datn_flash_sales` (
 `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `description` text,
  `start_at` datetime NOT NULL,
  `end_at` datetime NOT NULL,
  `status` enum('draft','scheduled','active','ended','cancelled') NOT NULL DEFAULT 'draft',
  `created_by` bigint UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 PRIMARY KEY (`id`),
 UNIQUE KEY `slug` (`slug`),
 KEY `created_by` (`created_by`),
 CONSTRAINT `datn_flash_sales_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `datn_users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- [09.13] Original table orders -> datn_orders
CREATE TABLE IF NOT EXISTS `datn_orders` (
 `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` bigint UNSIGNED DEFAULT NULL,
  `coupon_id` bigint UNSIGNED DEFAULT NULL,
  `shipping_method_id` bigint UNSIGNED DEFAULT NULL,
  `order_code` varchar(50) NOT NULL,
  `subtotal` decimal(12,2) NOT NULL DEFAULT '0.00',
  `discount_amount` decimal(12,2) NOT NULL DEFAULT '0.00',
  `shipping_fee` decimal(12,2) NOT NULL DEFAULT '0.00',
  `total_amount` decimal(12,2) NOT NULL DEFAULT '0.00',
  `shipping_name` varchar(255) NOT NULL,
  `shipping_phone` varchar(20) NOT NULL,
  `shipping_address` varchar(255) NOT NULL,
  `shipping_city` varchar(100) DEFAULT NULL,
  `shipping_district` varchar(100) DEFAULT NULL,
  `shipping_ward` varchar(100) DEFAULT NULL,
  `payment_method` enum('VNPAY','MOMO','COD') NOT NULL DEFAULT 'COD',
  `payment_status` enum('PENDING','PAID','FAILED','REFUNDED') NOT NULL DEFAULT 'PENDING',
  `order_status` enum('PENDING','CONFIRMED','PROCESSING','SHIPPING','DELIVERED','CANCELLED','RETURNED') NOT NULL DEFAULT 'PENDING',
  `customer_note` text,
  `cancelled_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 PRIMARY KEY (`id`),
 UNIQUE KEY `order_code` (`order_code`),
 KEY `user_id` (`user_id`),
 KEY `coupon_id` (`coupon_id`),
 KEY `shipping_method_id` (`shipping_method_id`),
 CONSTRAINT `datn_orders_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `datn_users` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
 CONSTRAINT `datn_orders_ibfk_2` FOREIGN KEY (`coupon_id`) REFERENCES `datn_coupons` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
 CONSTRAINT `datn_orders_ibfk_3` FOREIGN KEY (`shipping_method_id`) REFERENCES `datn_shipping_methods` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- [09.14] Original table permission_role -> datn_permission_role
CREATE TABLE IF NOT EXISTS `datn_permission_role` (
 `role_id` bigint UNSIGNED NOT NULL,
  `permission_id` bigint UNSIGNED NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
 PRIMARY KEY (`role_id`,`permission_id`),
 KEY `permission_id` (`permission_id`),
 CONSTRAINT `datn_permission_role_ibfk_1` FOREIGN KEY (`role_id`) REFERENCES `datn_roles` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
 CONSTRAINT `datn_permission_role_ibfk_2` FOREIGN KEY (`permission_id`) REFERENCES `datn_permissions` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- [09.15] Original table products -> datn_products
CREATE TABLE IF NOT EXISTS `datn_products` (
 `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `description` longtext,
  `brand_id` bigint UNSIGNED DEFAULT NULL,
  `category_id` bigint UNSIGNED DEFAULT NULL,
  `status` enum('draft','active','inactive','out_of_stock') NOT NULL DEFAULT 'draft',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 PRIMARY KEY (`id`),
 UNIQUE KEY `slug` (`slug`),
 KEY `idx_products_brand` (`brand_id`),
 KEY `idx_products_category` (`category_id`),
 CONSTRAINT `datn_products_ibfk_1` FOREIGN KEY (`brand_id`) REFERENCES `datn_brands` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
 CONSTRAINT `datn_products_ibfk_2` FOREIGN KEY (`category_id`) REFERENCES `datn_categories` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- [09.16] Original table role_user -> datn_role_user
CREATE TABLE IF NOT EXISTS `datn_role_user` (
 `user_id` bigint UNSIGNED NOT NULL,
  `role_id` bigint UNSIGNED NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
 PRIMARY KEY (`user_id`,`role_id`),
 KEY `role_id` (`role_id`),
 CONSTRAINT `datn_role_user_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `datn_users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
 CONSTRAINT `datn_role_user_ibfk_2` FOREIGN KEY (`role_id`) REFERENCES `datn_roles` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- [09.17] Original table user_addresses -> datn_user_addresses
CREATE TABLE IF NOT EXISTS `datn_user_addresses` (
 `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` bigint UNSIGNED NOT NULL,
  `label` varchar(50) DEFAULT NULL,
  `full_name` varchar(255) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `city` varchar(100) NOT NULL,
  `district` varchar(100) DEFAULT NULL,
  `ward` varchar(100) DEFAULT NULL,
  `address_line` varchar(255) NOT NULL,
  `is_default` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 PRIMARY KEY (`id`),
 KEY `user_id` (`user_id`),
 CONSTRAINT `datn_user_addresses_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `datn_users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- [09.18] Original table payments -> datn_payments
CREATE TABLE IF NOT EXISTS `datn_payments` (
 `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `order_id` bigint UNSIGNED NOT NULL,
  `transaction_code` varchar(100) DEFAULT NULL,
  `method` enum('VNPAY','MOMO','COD') NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `status` enum('PENDING','SUCCESS','FAILED','CANCELLED','REFUNDED') NOT NULL DEFAULT 'PENDING',
  `gateway_transaction_id` varchar(100) DEFAULT NULL,
  `response_data` longtext,
  `paid_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
 PRIMARY KEY (`id`),
 UNIQUE KEY `transaction_code` (`transaction_code`),
 KEY `order_id` (`order_id`),
 CONSTRAINT `datn_payments_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `datn_orders` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- [09.19] Original table product_compatibilities -> datn_product_compatibilities
CREATE TABLE IF NOT EXISTS `datn_product_compatibilities` (
 `product_id` bigint UNSIGNED NOT NULL,
  `compatible_product_id` bigint UNSIGNED NOT NULL,
  `note` varchar(255) DEFAULT NULL,
 PRIMARY KEY (`product_id`,`compatible_product_id`),
 KEY `compatible_product_id` (`compatible_product_id`),
 CONSTRAINT `datn_product_compatibilities_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `datn_products` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
 CONSTRAINT `datn_product_compatibilities_ibfk_2` FOREIGN KEY (`compatible_product_id`) REFERENCES `datn_products` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- [09.20] Original table product_variants -> datn_product_variants
CREATE TABLE IF NOT EXISTS `datn_product_variants` (
 `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `product_id` bigint UNSIGNED NOT NULL,
  `sku` varchar(100) NOT NULL,
  `price` decimal(12,2) NOT NULL DEFAULT '0.00',
  `compare_price` decimal(12,2) DEFAULT NULL,
  `cost_price` decimal(12,2) DEFAULT NULL,
  `image_url` varchar(255) DEFAULT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 PRIMARY KEY (`id`),
 UNIQUE KEY `sku` (`sku`),
 KEY `product_id` (`product_id`),
 CONSTRAINT `datn_product_variants_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `datn_products` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- [09.21] Original table cart_items -> datn_cart_items
CREATE TABLE IF NOT EXISTS `datn_cart_items` (
 `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `cart_id` bigint UNSIGNED NOT NULL,
  `variant_id` bigint UNSIGNED NOT NULL,
  `quantity` int UNSIGNED NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 PRIMARY KEY (`id`),
 UNIQUE KEY `uq_cart_variant` (`cart_id`,`variant_id`),
 KEY `variant_id` (`variant_id`),
 CONSTRAINT `datn_cart_items_ibfk_1` FOREIGN KEY (`cart_id`) REFERENCES `datn_carts` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
 CONSTRAINT `datn_cart_items_ibfk_2` FOREIGN KEY (`variant_id`) REFERENCES `datn_product_variants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- [09.22] Original table flash_sale_items -> datn_flash_sale_items
CREATE TABLE IF NOT EXISTS `datn_flash_sale_items` (
 `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `flash_sale_id` bigint UNSIGNED NOT NULL,
  `variant_id` bigint UNSIGNED NOT NULL,
  `sale_price` decimal(12,2) NOT NULL,
  `quantity_limit` int UNSIGNED DEFAULT NULL,
  `quantity_sold` int UNSIGNED NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
 PRIMARY KEY (`id`),
 UNIQUE KEY `uq_flash_sale_variant` (`flash_sale_id`,`variant_id`),
 KEY `variant_id` (`variant_id`),
 CONSTRAINT `datn_flash_sale_items_ibfk_1` FOREIGN KEY (`flash_sale_id`) REFERENCES `datn_flash_sales` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
 CONSTRAINT `datn_flash_sale_items_ibfk_2` FOREIGN KEY (`variant_id`) REFERENCES `datn_product_variants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- [09.23] Original table inventories -> datn_inventories
CREATE TABLE IF NOT EXISTS `datn_inventories` (
 `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `variant_id` bigint UNSIGNED NOT NULL,
  `quantity` int UNSIGNED NOT NULL DEFAULT '0',
  `reserved_quantity` int UNSIGNED NOT NULL DEFAULT '0',
  `available_quantity` int GENERATED ALWAYS AS (`quantity` - `reserved_quantity`) STORED,
  `min_stock` int UNSIGNED NOT NULL DEFAULT '0',
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 PRIMARY KEY (`id`),
 UNIQUE KEY `variant_id` (`variant_id`),
 CONSTRAINT `datn_inventories_ibfk_1` FOREIGN KEY (`variant_id`) REFERENCES `datn_product_variants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- [09.24] Original table inventory_transactions -> datn_inventory_transactions
CREATE TABLE IF NOT EXISTS `datn_inventory_transactions` (
 `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `variant_id` bigint UNSIGNED NOT NULL,
  `user_id` bigint UNSIGNED DEFAULT NULL,
  `transaction_type` enum('import','export','adjustment','reserve','release') NOT NULL,
  `quantity` int NOT NULL,
  `reference_type` varchar(50) DEFAULT NULL,
  `reference_id` bigint UNSIGNED DEFAULT NULL,
  `note` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
 PRIMARY KEY (`id`),
 KEY `variant_id` (`variant_id`),
 KEY `user_id` (`user_id`),
 CONSTRAINT `datn_inventory_transactions_ibfk_1` FOREIGN KEY (`variant_id`) REFERENCES `datn_product_variants` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
 CONSTRAINT `datn_inventory_transactions_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `datn_users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- [09.25] Original table order_items -> datn_order_items
CREATE TABLE IF NOT EXISTS `datn_order_items` (
 `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `order_id` bigint UNSIGNED NOT NULL,
  `product_variant_id` bigint UNSIGNED NOT NULL,
  `product_name` varchar(255) NOT NULL,
  `variant_name` varchar(255) DEFAULT NULL,
  `sku` varchar(100) DEFAULT NULL,
  `price` decimal(12,2) NOT NULL,
  `quantity` int UNSIGNED NOT NULL,
  `subtotal` decimal(12,2) NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
 PRIMARY KEY (`id`),
 KEY `order_id` (`order_id`),
 KEY `product_variant_id` (`product_variant_id`),
 CONSTRAINT `datn_order_items_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `datn_orders` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
 CONSTRAINT `datn_order_items_ibfk_2` FOREIGN KEY (`product_variant_id`) REFERENCES `datn_product_variants` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- [09.26] Original table product_images -> datn_product_images
CREATE TABLE IF NOT EXISTS `datn_product_images` (
 `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `product_id` bigint UNSIGNED NOT NULL,
  `variant_id` bigint UNSIGNED DEFAULT NULL,
  `image_url` varchar(255) NOT NULL,
  `alt_text` varchar(255) DEFAULT NULL,
  `sort_order` int NOT NULL DEFAULT '0',
  `is_primary` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
 PRIMARY KEY (`id`),
 KEY `product_id` (`product_id`),
 KEY `variant_id` (`variant_id`),
 CONSTRAINT `datn_product_images_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `datn_products` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
 CONSTRAINT `datn_product_images_ibfk_2` FOREIGN KEY (`variant_id`) REFERENCES `datn_product_variants` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- [09.27] Original table variant_attribute_values -> datn_variant_attribute_values
CREATE TABLE IF NOT EXISTS `datn_variant_attribute_values` (
 `variant_id` bigint UNSIGNED NOT NULL,
  `attribute_value_id` bigint UNSIGNED NOT NULL,
 PRIMARY KEY (`variant_id`,`attribute_value_id`),
 KEY `attribute_value_id` (`attribute_value_id`),
 CONSTRAINT `datn_variant_attribute_values_ibfk_1` FOREIGN KEY (`variant_id`) REFERENCES `datn_product_variants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
 CONSTRAINT `datn_variant_attribute_values_ibfk_2` FOREIGN KEY (`attribute_value_id`) REFERENCES `datn_attribute_values` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- [09.28] Original table reviews -> datn_reviews
CREATE TABLE IF NOT EXISTS `datn_reviews` (
 `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `product_id` bigint UNSIGNED NOT NULL,
  `user_id` bigint UNSIGNED NOT NULL,
  `order_item_id` bigint UNSIGNED DEFAULT NULL,
  `rating` tinyint UNSIGNED NOT NULL,
  `title` varchar(255) DEFAULT NULL,
  `content` text,
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `admin_reply` text,
  `replied_at` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 PRIMARY KEY (`id`),
 KEY `product_id` (`product_id`),
 KEY `user_id` (`user_id`),
 KEY `order_item_id` (`order_item_id`),
 CONSTRAINT `datn_reviews_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `datn_products` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
 CONSTRAINT `datn_reviews_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `datn_users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
 CONSTRAINT `datn_reviews_ibfk_3` FOREIGN KEY (`order_item_id`) REFERENCES `datn_order_items` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- [09.29] Original table review_images -> datn_review_images
CREATE TABLE IF NOT EXISTS `datn_review_images` (
 `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `review_id` bigint UNSIGNED NOT NULL,
  `image_url` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
 PRIMARY KEY (`id`),
 KEY `review_id` (`review_id`),
 CONSTRAINT `datn_review_images_ibfk_1` FOREIGN KEY (`review_id`) REFERENCES `datn_reviews` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- [10] Original role/permission SEED DATA (idempotent; no overwrite)
INSERT IGNORE INTO `datn_permissions` (`id`, `name`, `slug`, `description`, `created_at`, `updated_at`) VALUES
(1, 'Quản lý người dùng', 'users.manage', 'Quản lý tài khoản', '2026-10-26 11:53:22', '2026-10-26 11:53:22'),
(2, 'Quản lý phân quyền', 'roles.manage', 'Quản lý vai trò và quyền', '2026-10-26 11:53:22', '2026-10-26 11:53:22'),
(3, 'Quản lý danh mục', 'categories.manage', 'Quản lý danh mục', '2026-10-26 11:53:22', '2026-10-26 11:53:22'),
(4, 'Quản lý thương hiệu', 'brands.manage', 'Quản lý thương hiệu', '2026-10-26 11:53:22', '2026-10-26 11:53:22'),
(5, 'Quản lý sản phẩm', 'products.manage', 'Quản lý sản phẩm', '2026-10-26 11:53:22', '2026-10-26 11:53:22'),
(6, 'Quản lý kho', 'inventory.manage', 'Quản lý tồn kho', '2026-10-26 11:53:22', '2026-10-26 11:53:22'),
(7, 'Quản lý đơn hàng', 'orders.manage', 'Quản lý đơn hàng', '2026-10-26 11:53:22', '2026-10-26 11:53:22'),
(8, 'Quản lý Flash Sale', 'flash_sales.manage', 'Quản lý khuyến mãi', '2026-10-26 11:53:22', '2026-10-26 11:53:22'),
(9, 'Quản lý đánh giá', 'reviews.manage', 'Quản lý đánh giá', '2026-10-26 11:53:22', '2026-10-26 11:53:22'),
(10, 'Xem báo cáo', 'reports.view', 'Xem báo cáo thống kê', '2026-10-26 11:53:22', '2026-10-26 11:53:22');

INSERT IGNORE INTO `datn_roles` (`id`, `name`, `slug`, `description`, `is_system`, `created_at`, `updated_at`) VALUES
(1, 'Quản trị viên', 'admin', 'Toàn quyền quản trị hệ thống', 1, '2026-10-26 11:53:22', '2026-10-26 11:53:22'),
(2, 'Nhân viên', 'staff', 'Quản lý sản phẩm, kho và đơn hàng', 1, '2026-10-26 11:53:22', '2026-10-26 11:53:22'),
(3, 'Khách hàng', 'customer', 'Mua hàng và quản lý tài khoản', 1, '2026-10-26 11:53:22', '2026-10-26 11:53:22');

INSERT IGNORE INTO `datn_permission_role` (`role_id`, `permission_id`, `created_at`) VALUES
(1, 1, '2026-10-26 11:53:22'),
(1, 2, '2026-10-26 11:53:22'),
(1, 3, '2026-10-26 11:53:22'),
(1, 4, '2026-10-26 11:53:22'),
(1, 5, '2026-10-26 11:53:22'),
(1, 6, '2026-10-26 11:53:22'),
(1, 7, '2026-10-26 11:53:22'),
(1, 8, '2026-10-26 11:53:22'),
(1, 9, '2026-10-26 11:53:22'),
(1, 10, '2026-10-26 11:53:22'),
(2, 3, '2026-10-26 11:53:22'),
(2, 4, '2026-10-26 11:53:22'),
(2, 5, '2026-10-26 11:53:22'),
(2, 6, '2026-10-26 11:53:22'),
(2, 7, '2026-10-26 11:53:22'),
(2, 8, '2026-10-26 11:53:22'),
(2, 9, '2026-10-26 11:53:22'),
(2, 10, '2026-10-26 11:53:22');

-- [11] COMPATIBILITY CHECKS (read only; results show LIVE schema differences)
-- If any required column below is missing, the existing database needs a
-- carefully reviewed ALTER TABLE migration before PHP can use it.
SELECT required.table_name, required.column_name,
 CASE WHEN actual.COLUMN_NAME IS NULL THEN 'MISSING - needs migration' ELSE 'OK' END AS compatibility
FROM (
 SELECT 'users' table_name,'password_hash' column_name UNION ALL
 SELECT 'users','role' UNION ALL
 SELECT 'products','sku' UNION ALL
 SELECT 'products','price' UNION ALL
 SELECT 'products','stock_quantity' UNION ALL
 SELECT 'products','is_active' UNION ALL
 SELECT 'orders','status' UNION ALL
 SELECT 'orders','completed_at' UNION ALL
 SELECT 'order_items','product_id' UNION ALL
 SELECT 'order_items','unit_price' UNION ALL
 SELECT 'product_reviews','moderation_reason' UNION ALL
 SELECT 'product_review_images','image_path' UNION ALL
 SELECT 'home_banners','image_path' UNION ALL
 SELECT 'flash_sales','starts_at' UNION ALL
 SELECT 'flash_sales','ends_at' UNION ALL
 SELECT 'flash_sale_products','sale_price'
) AS required
LEFT JOIN information_schema.COLUMNS actual
 ON actual.TABLE_SCHEMA=DATABASE()
 AND actual.TABLE_NAME=required.table_name
 AND actual.COLUMN_NAME=required.column_name
ORDER BY required.table_name, required.column_name;

-- [12] ONGOING APPLICATION RULES (not enforceable by SQL file alone):
-- a) Real app login reads users.password_hash + users.role; seed does not make admin.
-- b) Verified purchase uses orders.status='completed' and order_items.product_id.
-- c) Only product_reviews.status='approved' should be public on storefront.
-- d) Flash Sale checkout must recheck NOW(), stock, and valid sale_price server-side.
-- e) Do not schedule overlapping active flash sales for same product without policy.
-- f) To migrate datn_* data, write explicit ID crosswalk and status transformations.
-- END OF UNIFIED FILE
