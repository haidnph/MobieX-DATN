<?php

namespace App\Services\Search;

/**
 * Data Transfer Object representing the analyzed search intent of a user query.
 */
class SearchIntent
{
    public string $rawQuery;

    public string $normalizedQuery;

    public bool $hasTypo = false;

    public ?string $suggestedQuery = null;

    public ?string $modelAlias = null;

    public ?int $brandId = null;

    public ?string $brandName = null;

    public ?int $categoryId = null;

    public ?string $categoryName = null;

    public ?float $minPrice = null;

    public ?float $maxPrice = null;

    public ?string $ram = null;

    public ?string $storage = null;

    public array $specs = [];

    public array $intents = [];

    public array $criteria = [];

    public array $searchTokens = [];

    public function toArray(): array
    {
        return [
            'original_query' => $this->rawQuery,
            'normalized_query' => $this->normalizedQuery,
            'has_typo' => $this->hasTypo,
            'suggested_query' => $this->suggestedQuery,
            'model_alias' => $this->modelAlias,
            'brand' => $this->brandName ? ['id' => $this->brandId, 'name' => $this->brandName] : null,
            'category' => $this->categoryName ? ['id' => $this->categoryId, 'name' => $this->categoryName] : null,
            'min_price' => $this->minPrice,
            'max_price' => $this->maxPrice,
            'ram' => $this->ram,
            'storage' => $this->storage,
            'specs' => $this->specs,
            'intents' => $this->intents,
            'criteria' => $this->criteria,
            'search_tokens' => $this->searchTokens,
        ];
    }
}
