<?php

namespace Database\Seeders;

use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\Category;
use Illuminate\Database\Seeder;

class AttributeSeeder extends Seeder
{
    public function run(): void
    {
        $attributesData = [
            'color' => [
                'name' => 'Màu sắc',
                'display_type' => 'color',
                'sort_order' => 1,
                'is_active' => true,
                'values' => [
                    ['code' => 'black', 'value' => 'Đen', 'color_code' => '#000000', 'sort_order' => 1],
                    ['code' => 'white', 'value' => 'Trắng', 'color_code' => '#FFFFFF', 'sort_order' => 2],
                    ['code' => 'silver', 'value' => 'Bạc', 'color_code' => '#C0C0C0', 'sort_order' => 3],
                    ['code' => 'titanium', 'value' => 'Titan Tự Nhiên', 'color_code' => '#B8B3A7', 'sort_order' => 4],
                ],
            ],
            'ram' => [
                'name' => 'RAM',
                'display_type' => 'select',
                'sort_order' => 2,
                'is_active' => true,
                'values' => [
                    ['code' => '4gb', 'value' => '4GB', 'color_code' => null, 'sort_order' => 1],
                    ['code' => '6gb', 'value' => '6GB', 'color_code' => null, 'sort_order' => 2],
                    ['code' => '8gb', 'value' => '8GB', 'color_code' => null, 'sort_order' => 3],
                    ['code' => '12gb', 'value' => '12GB', 'color_code' => null, 'sort_order' => 4],
                    ['code' => '16gb', 'value' => '16GB', 'color_code' => null, 'sort_order' => 5],
                ],
            ],
            'storage' => [
                'name' => 'Dung lượng lưu trữ',
                'display_type' => 'select',
                'sort_order' => 3,
                'is_active' => true,
                'values' => [
                    ['code' => '64gb', 'value' => '64GB', 'color_code' => null, 'sort_order' => 1],
                    ['code' => '128gb', 'value' => '128GB', 'color_code' => null, 'sort_order' => 2],
                    ['code' => '256gb', 'value' => '256GB', 'color_code' => null, 'sort_order' => 3],
                    ['code' => '512gb', 'value' => '512GB', 'color_code' => null, 'sort_order' => 4],
                    ['code' => '1tb', 'value' => '1TB', 'color_code' => null, 'sort_order' => 5],
                ],
            ],
            'style' => [
                'name' => 'Kiểu dáng',
                'display_type' => 'select',
                'sort_order' => 4,
                'is_active' => true,
                'values' => [
                    ['code' => 'standard', 'value' => 'Tiêu chuẩn', 'color_code' => null, 'sort_order' => 1],
                    ['code' => 'premium', 'value' => 'Cao cấp', 'color_code' => null, 'sort_order' => 2],
                ],
            ],
        ];

        $attributeModels = [];

        foreach ($attributesData as $code => $attrData) {
            $attribute = Attribute::query()->updateOrCreate(
                ['code' => $code],
                [
                    'name' => $attrData['name'],
                    'display_type' => $attrData['display_type'],
                    'sort_order' => $attrData['sort_order'],
                    'is_active' => $attrData['is_active'],
                ]
            );

            $attributeModels[$code] = $attribute;

            foreach ($attrData['values'] as $valData) {
                AttributeValue::query()->updateOrCreate(
                    [
                        'attribute_id' => $attribute->id,
                        'code' => $valData['code'],
                    ],
                    [
                        'value' => $valData['value'],
                        'color_code' => $valData['color_code'],
                        'sort_order' => $valData['sort_order'],
                        'is_active' => true,
                    ]
                );
            }
        }

        // Category Attribute mappings
        $phoneCategory = Category::where('slug', 'dien-thoai')->first();
        $accessoryCategory = Category::where('slug', 'phu-kien')->first();

        if ($phoneCategory) {
            $mappings = [
                'color' => ['is_required' => 1, 'is_variant_axis' => 1, 'sort_order' => 1],
                'ram' => ['is_required' => 1, 'is_variant_axis' => 1, 'sort_order' => 2],
                'storage' => ['is_required' => 1, 'is_variant_axis' => 1, 'sort_order' => 3],
            ];

            foreach ($mappings as $code => $pivot) {
                if (isset($attributeModels[$code])) {
                    $phoneCategory->attributes()->syncWithoutDetaching([
                        $attributeModels[$code]->id => $pivot,
                    ]);
                }
            }
        }

        if ($accessoryCategory) {
            $accessoryMappings = [
                'color' => ['is_required' => 0, 'is_variant_axis' => 1, 'sort_order' => 1],
                'style' => ['is_required' => 0, 'is_variant_axis' => 1, 'sort_order' => 2],
            ];

            foreach ($accessoryMappings as $code => $pivot) {
                if (isset($attributeModels[$code])) {
                    $accessoryCategory->attributes()->syncWithoutDetaching([
                        $attributeModels[$code]->id => $pivot,
                    ]);
                }
            }
        }
    }
}
