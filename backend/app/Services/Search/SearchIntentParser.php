<?php

namespace App\Services\Search;

use App\Models\Brand;
use App\Models\Category;
use Illuminate\Support\Facades\Cache;

/**
 * Natural Language Query Parser & Search Intent Analyzer for MobieX.
 */
class SearchIntentParser
{
    /**
     * Parse raw search query into structured search intent.
     */
    public function parse(string $query): SearchIntent
    {
        $intent = new SearchIntent;
        $intent->rawQuery = trim($query);

        if ($intent->rawQuery === '') {
            $intent->normalizedQuery = '';

            return $intent;
        }

        // 1. Model Alias Extraction & Normalization (e.g. "ip16pm", "s24u", "ip16pm 256gb")
        [$workingText, $detectedModel] = SearchSynonyms::replaceModelAliases($intent->rawQuery);
        if ($detectedModel) {
            $intent->modelAlias = $detectedModel;
            $intent->criteria[] = [
                'type' => 'alias',
                'label' => 'Bí danh',
                'value' => $detectedModel,
            ];
            // If the query was purely the alias, fast-path
            if (mb_strtolower(trim($workingText)) === mb_strtolower($detectedModel)) {
                $intent->normalizedQuery = $detectedModel;
                $intent->searchTokens = array_values(array_filter(preg_split('/\s+/', $detectedModel)));

                return $intent;
            }
        }

        // 2. Typo correction
        [$correctedQuery, $hasTypo] = SearchSynonyms::correctTypos($workingText);
        $intent->hasTypo = $hasTypo;
        if ($hasTypo) {
            $intent->suggestedQuery = $correctedQuery;
        }

        // 3. Slang and Category Normalization
        $workingText = SearchSynonyms::normalizeSlangAndCategories($correctedQuery);

        // 4. Budget / Price Range Extraction
        $workingText = $this->extractPriceRange($workingText, $intent);

        // 5. Hardware Specifications Extraction (RAM, Storage, Battery, Wattage, Display)
        $workingText = $this->extractHardwareSpecs($workingText, $intent);

        // 6. User Needs / Use Cases Intent Extraction
        $workingText = $this->extractUseCases($workingText, $intent);

        // 7. Brand and Category Matching
        $workingText = $this->extractBrandAndCategory($workingText, $intent);

        // 8. Clean up conversational filler words
        $cleanRemaining = $this->removeFillerWords($workingText);

        $intent->normalizedQuery = trim($cleanRemaining);
        $intent->searchTokens = array_values(array_filter(
            preg_split('/\s+/', $intent->normalizedQuery),
            fn ($t) => mb_strlen($t) > 0
        ));

        return $intent;
    }

    /**
     * Extract budget and price ranges from natural language.
     * E.g. "dưới 15 triệu", "từ 5 đến 10 triệu", "khoảng 20tr"
     */
    private function extractPriceRange(string $text, SearchIntent $intent): string
    {
        // Case: "từ X đến Y triệu" hoặc "X - Y triệu"
        if (preg_match('/(?:từ\s*)?(\d+)\s*(?:triệu|tr)?\s*(?:đến|-)\s*(\d+)\s*(triệu|tr)/iu', $text, $m)) {
            $minVal = (float) $m[1] * 1000000;
            $maxVal = (float) $m[2] * 1000000;
            if ($minVal < $maxVal) {
                $intent->minPrice = $minVal;
                $intent->maxPrice = $maxVal;
                $intent->criteria[] = [
                    'type' => 'price',
                    'label' => 'Ngân sách',
                    'value' => number_format($minVal).'₫ – '.number_format($maxVal).'₫',
                    'min' => $minVal,
                    'max' => $maxVal,
                ];

                return preg_replace('/'.preg_quote($m[0], '/').'/iu', ' ', $text);
            }
        }

        // Case: "dưới X triệu" / "< X tr" / "<= X triệu"
        if (preg_match('/(?:dưới|nhỏ hơn|<|<=)\s*(\d+)\s*(?:triệu|tr|m|k)/iu', $text, $m)) {
            $maxVal = (float) $m[1] * 1000000;
            $intent->maxPrice = $maxVal;
            $intent->criteria[] = [
                'type' => 'price',
                'label' => 'Ngân sách',
                'value' => '≤ '.number_format($maxVal).'₫',
                'max' => $maxVal,
            ];

            return preg_replace('/'.preg_quote($m[0], '/').'/iu', ' ', $text);
        }

        // Case: "trên X triệu" / "> X tr"
        if (preg_match('/(?:trên|lớn hơn|>|>=)\s*(\d+)\s*(?:triệu|tr|m|k)/iu', $text, $m)) {
            $minVal = (float) $m[1] * 1000000;
            $intent->minPrice = $minVal;
            $intent->criteria[] = [
                'type' => 'price',
                'label' => 'Ngân sách',
                'value' => '≥ '.number_format($minVal).'₫',
                'min' => $minVal,
            ];

            return preg_replace('/'.preg_quote($m[0], '/').'/iu', ' ', $text);
        }

        // Case: "khoảng / tầm X triệu"
        if (preg_match('/(?:khoảng|tầm|cỡ)\s*(\d+)\s*(?:triệu|tr|m)/iu', $text, $m)) {
            $center = (float) $m[1] * 1000000;
            $minVal = max(0, $center * 0.8);
            $maxVal = $center * 1.2;
            $intent->minPrice = $minVal;
            $intent->maxPrice = $maxVal;
            $intent->criteria[] = [
                'type' => 'price',
                'label' => 'Ngân sách',
                'value' => 'Tầm '.number_format($center).'₫ (±20%)',
                'min' => $minVal,
                'max' => $maxVal,
            ];

            return preg_replace('/'.preg_quote($m[0], '/').'/iu', ' ', $text);
        }

        return $text;
    }

    /**
     * Extract hardware specs (RAM, Storage, Battery, Wattage, Refresh rate, Connector).
     */
    private function extractHardwareSpecs(string $text, SearchIntent $intent): string
    {
        // 1. RAM: e.g. "ram 12gb", "12gb ram", "ram 8gb"
        if (preg_match('/\b(?:ram\s*)?(4|6|8|12|16|24)\s*gb\s*(?:ram)?\b/iu', $text, $m)) {
            // Check if user specifically wrote "ram" or if it matches RAM sizes
            $isExplicitRam = stripos($m[0], 'ram') !== false;
            if ($isExplicitRam || in_array((int) $m[1], [6, 12, 16, 24])) {
                $ramVal = $m[1].'GB';
                $intent->ram = $ramVal;
                $intent->criteria[] = [
                    'type' => 'ram',
                    'label' => 'RAM',
                    'value' => $ramVal,
                ];
                $text = preg_replace('/'.preg_quote($m[0], '/').'/iu', ' ', $text);
            }
        }

        // 2. Storage / ROM: e.g. "256gb", "128gb", "512gb", "1tb", "bộ nhớ 256gb"
        if (preg_match('/\b(?:bộ nhớ|rom|dung lượng)?\s*(64|128|256|512)\s*gb\b/iu', $text, $m)) {
            $storageVal = $m[1].'GB';
            $intent->storage = $storageVal;
            $intent->criteria[] = [
                'type' => 'storage',
                'label' => 'Bộ nhớ trong',
                'value' => $storageVal,
            ];
            $text = preg_replace('/'.preg_quote($m[0], '/').'/iu', ' ', $text);
        } elseif (preg_match('/\b(?:bộ nhớ|dung lượng)?\s*1\s*tb\b/iu', $text, $m)) {
            $intent->storage = '1TB';
            $intent->criteria[] = [
                'type' => 'storage',
                'label' => 'Bộ nhớ trong',
                'value' => '1TB',
            ];
            $text = preg_replace('/'.preg_quote($m[0], '/').'/iu', ' ', $text);
        }

        // 3. Battery Capacity: e.g. "pin 5000mah", "pin trâu", "pin khủng"
        if (preg_match('/\bpin\s*(\d{4,5})\s*(?:mah)?\b/iu', $text, $m)) {
            $capacity = (int) $m[1];
            $intent->specs['battery_min'] = $capacity;
            $intent->criteria[] = [
                'type' => 'spec',
                'label' => 'Dung lượng pin',
                'value' => "≥ {$capacity} mAh",
            ];
            $text = preg_replace('/'.preg_quote($m[0], '/').'/iu', ' ', $text);
        } elseif (preg_match('/\b(pin trâu|pin lâu|pin khủng|pin dai)\b/iu', $text, $m)) {
            $intent->specs['battery_min'] = 4500;
            $intent->intents[] = 'battery';
            $intent->criteria[] = [
                'type' => 'need',
                'label' => 'Nhu cầu',
                'value' => 'Pin trâu (≥ 4.500 mAh)',
            ];
            $text = preg_replace('/'.preg_quote($m[0], '/').'/iu', ' ', $text);
        }

        // 4. Charging Power: e.g. "sạc 65w", "65w", "45w", "20w", "100w"
        if (preg_match('/\b(?:sạc\s*)?(\d{2,3})\s*w\b/iu', $text, $m)) {
            $watt = (int) $m[1];
            if ($watt >= 15 && $watt <= 240) {
                $intent->specs['charging_wattage'] = $watt;
                $intent->criteria[] = [
                    'type' => 'spec',
                    'label' => 'Công suất sạc',
                    'value' => "{$watt}W",
                ];
                $text = preg_replace('/'.preg_quote($m[0], '/').'/iu', ' ', $text);
            }
        }

        // 5. Screen Refresh Rate: e.g. "120hz", "90hz", "144hz"
        if (preg_match('/\b(?:màn hình\s*)?(120|90|144)\s*hz\b/iu', $text, $m)) {
            $hz = (int) $m[1];
            $intent->specs['refresh_rate'] = $hz;
            $intent->criteria[] = [
                'type' => 'spec',
                'label' => 'Tần số quét',
                'value' => "{$hz}Hz",
            ];
            $text = preg_replace('/'.preg_quote($m[0], '/').'/iu', ' ', $text);
        }

        // 6. Connector & Cable Length: e.g. "type-c", "type c", "lightning", "dài 2m"
        if (preg_match('/\b(type-c|type c|usb-c)\b/iu', $text, $m)) {
            $intent->specs['connector_type'] = 'Type-C';
            $intent->criteria[] = [
                'type' => 'spec',
                'label' => 'Cổng kết nối',
                'value' => 'Type-C',
            ];
            $text = preg_replace('/'.preg_quote($m[0], '/').'/iu', ' ', $text);
        } elseif (preg_match('/\blightning\b/iu', $text, $m)) {
            $intent->specs['connector_type'] = 'Lightning';
            $intent->criteria[] = [
                'type' => 'spec',
                'label' => 'Cổng kết nối',
                'value' => 'Lightning',
            ];
            $text = preg_replace('/'.preg_quote($m[0], '/').'/iu', ' ', $text);
        }

        if (preg_match('/\b(?:dài\s*)?(\d(?:\.\d)?)\s*m\b/iu', $text, $m)) {
            $len = (float) $m[1];
            $intent->specs['cable_length'] = $len;
            $intent->criteria[] = [
                'type' => 'spec',
                'label' => 'Chiều dài cáp',
                'value' => "{$len}m",
            ];
            $text = preg_replace('/'.preg_quote($m[0], '/').'/iu', ' ', $text);
        }

        // 7. Chipset: e.g. "snapdragon 8 gen 3", "snapdragon", "a18 pro", "a16 bionic"
        if (preg_match('/\b(snapdragon(?:\s*8\s*gen\s*3|\s*8\s*gen\s*2)?|a18\s*pro|a16\s*bionic|dimensity)\b/iu', $text, $m)) {
            $chip = trim($m[1]);
            $intent->specs['chipset'] = $chip;
            $intent->criteria[] = [
                'type' => 'spec',
                'label' => 'Vi xử lý',
                'value' => $chip,
            ];
            $text = preg_replace('/'.preg_quote($m[0], '/').'/iu', ' ', $text);
        }

        return $text;
    }

    /**
     * Extract user use cases and consumer needs.
     */
    private function extractUseCases(string $text, SearchIntent $intent): string
    {
        // Gaming
        if (preg_match('/\b(chơi game|gaming|game tốt|chiến game|đồ họa cao)\b/iu', $text, $m)) {
            $intent->intents[] = 'gaming';
            $intent->criteria[] = [
                'type' => 'need',
                'label' => 'Nhu cầu',
                'value' => 'Chơi game mượt mà',
                'key' => 'gaming',
            ];
            $text = preg_replace('/'.preg_quote($m[0], '/').'/iu', ' ', $text);
        }

        // Photography
        if (preg_match('/\b(chụp ảnh đẹp|chụp hình đẹp|camera đẹp|quay phim|chụp đêm)\b/iu', $text, $m)) {
            $intent->intents[] = 'photography';
            $intent->criteria[] = [
                'type' => 'need',
                'label' => 'Nhu cầu',
                'value' => 'Chụp ảnh sắc nét',
                'key' => 'photography',
            ];
            $text = preg_replace('/'.preg_quote($m[0], '/').'/iu', ' ', $text);
        }

        // Sports / Fitness
        if (preg_match('/\b(thể thao|tập gym|chạy bộ|chống nước)\b/iu', $text, $m)) {
            $intent->intents[] = 'sports';
            $intent->criteria[] = [
                'type' => 'need',
                'label' => 'Nhu cầu',
                'value' => 'Thể thao & Vận động',
                'key' => 'sports',
            ];
            $text = preg_replace('/'.preg_quote($m[0], '/').'/iu', ' ', $text);
        }

        return $text;
    }

    /**
     * Match Brand and Category names from database.
     */
    private function extractBrandAndCategory(string $text, SearchIntent $intent): string
    {
        $brands = Cache::remember('search_all_brands_v2', 600, function () {
            return Brand::where('is_active', true)->get(['id', 'name', 'slug'])->toArray();
        });

        foreach ($brands as $b) {
            $bName = is_array($b) ? ($b['name'] ?? '') : ($b->name ?? '');
            $bId = is_array($b) ? ($b['id'] ?? null) : ($b->id ?? null);
            if (! $bName) {
                continue;
            }
            $pattern = '/\b'.preg_quote($bName, '/').'\b/iu';
            if (preg_match($pattern, $text)) {
                $intent->brandId = $bId;
                $intent->brandName = $bName;
                $intent->criteria[] = [
                    'type' => 'brand',
                    'label' => 'Thương hiệu',
                    'value' => $bName,
                    'id' => $bId,
                ];
                $text = preg_replace($pattern, ' ', $text, 1);
                break;
            }
        }

        $categories = Cache::remember('search_all_categories_v2', 600, function () {
            return Category::where('is_active', true)->get(['id', 'name', 'slug'])->toArray();
        });

        foreach ($categories as $c) {
            $cName = is_array($c) ? ($c['name'] ?? '') : ($c->name ?? '');
            $cId = is_array($c) ? ($c['id'] ?? null) : ($c->id ?? null);
            if (! $cName) {
                continue;
            }
            $pattern = '/\b'.preg_quote($cName, '/').'\b/iu';
            if (preg_match($pattern, $text)) {
                $intent->categoryId = $cId;
                $intent->categoryName = $cName;
                $intent->criteria[] = [
                    'type' => 'category',
                    'label' => 'Danh mục',
                    'value' => $cName,
                    'id' => $cId,
                ];
                $text = preg_replace($pattern, ' ', $text, 1);
                break;
            }
        }

        return $text;
    }

    /**
     * Remove conversational stop words and fillers.
     */
    private function removeFillerWords(string $text): string
    {
        $fillers = [
            'tôi cần', 'toi can', 'tôi muốn', 'toi muon', 'muốn mua', 'muon mua',
            'tìm kiếm', 'tim kiem', 'tìm', 'tim', 'cần mua', 'can mua',
            'loại', 'loai', 'dòng', 'dong', 'nào', 'nao', 'tốt nhất', 'tot nhat',
            'tốt', 'tot', 'chất lượng', 'chat luong', 'chính hãng', 'chinh hang',
            'giá rẻ', 'gia re', 'giá tốt', 'gia tot', 'giá', 'gia', 'mới nhất', 'moi nhat',
            'có', 'co', 'cho', 'với', 'voi', 'và', 'va', 'để', 'de',
            'sạc nhanh', 'sac nhanh', 'nhanh', 'bản', 'ban', 'màu', 'mau',
        ];

        foreach ($fillers as $f) {
            $text = preg_replace('/\b'.preg_quote($f, '/').'\b/iu', ' ', $text);
        }

        // Collapse whitespace
        return trim(preg_replace('/\s+/', ' ', $text));
    }
}
