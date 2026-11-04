INSERT INTO `shipping_methods` (`name`,`code`,`description`,`fee`,`estimated_days`,`is_active`)
SELECT 'Giao tiêu chuẩn','standard','Giao hàng tiêu chuẩn',30000,'2-4 ngày',1
WHERE NOT EXISTS (SELECT 1 FROM `shipping_methods` WHERE `code`='standard');

INSERT INTO `shipping_methods` (`name`,`code`,`description`,`fee`,`estimated_days`,`is_active`)
SELECT 'Giao nhanh','express','Giao hàng ưu tiên',60000,'1-2 ngày',1
WHERE NOT EXISTS (SELECT 1 FROM `shipping_methods` WHERE `code`='express');

INSERT INTO `shipping_methods` (`name`,`code`,`description`,`fee`,`estimated_days`,`is_active`)
SELECT 'Nhận tại cửa hàng','pickup','Nhận hàng tại cửa hàng',0,'Trong ngày',1
WHERE NOT EXISTS (SELECT 1 FROM `shipping_methods` WHERE `code`='pickup');
