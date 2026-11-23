<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Support\Collection;

class ProductComparisonService
{
    private Collection $categories;

    public function __construct()
    {
        $this->categories = Category::query()->get(['id', 'parent_id', 'slug'])->keyBy('id');
    }

    public function groupForCategory(int|string|null $categoryId): ?string
    {
        $category = $this->categories->get((int) $categoryId);
        if (! $category) {
            return null;
        }

        $trail = [$category];
        while ($category->parent_id && ($parent = $this->categories->get((int) $category->parent_id))) {
            $trail[] = $parent;
            $category = $parent;
        }

        $root = end($trail);
        // Phone brand subcategories are one comparable device family. Accessories
        // use their first child category so chargers and audio accessories differ.
        if ($root->slug === 'phu-kien' && count($trail) > 1) {
            return 'category:'.$trail[count($trail) - 2]->id;
        }

        return 'category:'.$root->id;
    }

    public function attachGroups(iterable $products): void
    {
        foreach ($products as $product) {
            if ($product instanceof Product) {
                $product->setAttribute('compare_group', $this->groupForCategory($product->category_id));
            }
        }
    }
}
