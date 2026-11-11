<?php

namespace Database\Seeders;

use App\Models\ProductSpecificationDefinition;
use Illuminate\Database\Seeder;

class ProductSpecificationDefinitionSeeder extends Seeder
{
    public function run(): void
    {
        $definitions = [
            ['code' => 'chipset', 'name' => 'Vi xử lý', 'value_type' => 'text', 'unit' => null, 'applies_to' => 'phone', 'is_comparable' => true, 'sort_order' => 1],
            ['code' => 'display_size', 'name' => 'Kích thước màn hình', 'value_type' => 'number', 'unit' => 'inch', 'applies_to' => 'phone', 'is_comparable' => true, 'sort_order' => 2],
            ['code' => 'display_resolution', 'name' => 'Độ phân giải màn hình', 'value_type' => 'text', 'unit' => null, 'applies_to' => 'phone', 'is_comparable' => true, 'sort_order' => 3],
            ['code' => 'battery_capacity', 'name' => 'Dung lượng pin', 'value_type' => 'number', 'unit' => 'mAh', 'applies_to' => 'phone', 'is_comparable' => true, 'sort_order' => 4],
            ['code' => 'rear_camera', 'name' => 'Camera sau', 'value_type' => 'text', 'unit' => null, 'applies_to' => 'phone', 'is_comparable' => true, 'sort_order' => 5],
            ['code' => 'front_camera', 'name' => 'Camera trước', 'value_type' => 'text', 'unit' => null, 'applies_to' => 'phone', 'is_comparable' => true, 'sort_order' => 6],
            ['code' => 'operating_system', 'name' => 'Hệ điều hành', 'value_type' => 'text', 'unit' => null, 'applies_to' => 'phone', 'is_comparable' => true, 'sort_order' => 7],
            ['code' => 'water_resistance', 'name' => 'Kháng nước/bụi', 'value_type' => 'text', 'unit' => null, 'applies_to' => 'phone', 'is_comparable' => true, 'sort_order' => 8],
            ['code' => 'weight', 'name' => 'Trọng lượng', 'value_type' => 'number', 'unit' => 'g', 'applies_to' => 'all', 'is_comparable' => true, 'sort_order' => 9],
            ['code' => 'connector_type', 'name' => 'Chuẩn kết nối', 'value_type' => 'text', 'unit' => null, 'applies_to' => 'accessory', 'is_comparable' => false, 'sort_order' => 10],
            ['code' => 'charging_power', 'name' => 'Công suất sạc', 'value_type' => 'number', 'unit' => 'W', 'applies_to' => 'accessory', 'is_comparable' => false, 'sort_order' => 11],
            ['code' => 'cable_length', 'name' => 'Chiều dài cáp', 'value_type' => 'number', 'unit' => 'm', 'applies_to' => 'accessory', 'is_comparable' => false, 'sort_order' => 12],
            ['code' => 'material', 'name' => 'Chất liệu', 'value_type' => 'text', 'unit' => null, 'applies_to' => 'accessory', 'is_comparable' => false, 'sort_order' => 13],
        ];

        foreach ($definitions as $def) {
            ProductSpecificationDefinition::query()->updateOrCreate(
                ['code' => $def['code']],
                array_merge($def, ['is_active' => true])
            );
        }
    }
}
