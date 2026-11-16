<?php

namespace App\Services\Search;

/**
 * Enterprise Synonym, Alias & Typo Dictionary for MobieX Universal Smart Search.
 */
class SearchSynonyms
{
    /**
     * Brand aliases & Vietnamese tech slang.
     */
    public const BRAND_ALIASES = [
        'táo' => 'Apple',
        'tao' => 'Apple',
        'sam' => 'Samsung',
        'sam sung' => 'Samsung',
        'mì xào' => 'Xiaomi',
        'mi xao' => 'Xiaomi',
        'xiao mi' => 'Xiaomi',
        'bê sớt' => 'Baseus',
        'be sot' => 'Baseus',
        'an kơ' => 'Anker',
        'an ko' => 'Anker',
    ];

    /**
     * Exact device model aliases & shortcuts (case-insensitive).
     */
    public const MODEL_ALIASES = [
        // iPhone series
        'ip16pm' => 'iPhone 16 Pro Max',
        'ip16 prm' => 'iPhone 16 Pro Max',
        'ip16 pro max' => 'iPhone 16 Pro Max',
        'ip16p' => 'iPhone 16 Pro',
        'ip16 pro' => 'iPhone 16 Pro',
        'ip16 plus' => 'iPhone 16 Plus',
        'ip16' => 'iPhone 16',
        'iphone 16 prm' => 'iPhone 16 Pro Max',
        'ip 16 prm' => 'iPhone 16 Pro Max',
        'ip 16 pro max' => 'iPhone 16 Pro Max',
        'ip15pm' => 'iPhone 15 Pro Max',
        'ip15 prm' => 'iPhone 15 Pro Max',
        'ip15 pro max' => 'iPhone 15 Pro Max',
        'ip15p' => 'iPhone 15 Pro',
        'ip15 pro' => 'iPhone 15 Pro',
        'ip15' => 'iPhone 15',
        'iphone 15 prm' => 'iPhone 15 Pro Max',
        'ip14pm' => 'iPhone 14 Pro Max',
        'ip14 prm' => 'iPhone 14 Pro Max',
        'ip13pm' => 'iPhone 13 Pro Max',
        'prm' => 'Pro Max',
        'promax' => 'Pro Max',

        // Samsung Galaxy series
        's24u' => 'Samsung Galaxy S24 Ultra',
        's24 ultra' => 'Samsung Galaxy S24 Ultra',
        's24+' => 'Samsung Galaxy S24 Plus',
        's24 plus' => 'Samsung Galaxy S24 Plus',
        's24' => 'Samsung Galaxy S24',
        's23u' => 'Samsung Galaxy S23 Ultra',
        's23 ultra' => 'Samsung Galaxy S23 Ultra',
        'zfold6' => 'Samsung Galaxy Z Fold 6',
        'z fold 6' => 'Samsung Galaxy Z Fold 6',
        'fold 6' => 'Samsung Galaxy Z Fold 6',
        'zflip6' => 'Samsung Galaxy Z Flip 6',
        'z flip 6' => 'Samsung Galaxy Z Flip 6',
        'flip 6' => 'Samsung Galaxy Z Flip 6',

        // Xiaomi & Sony
        'mi 14' => 'Xiaomi 14',
        'mi 14 pro' => 'Xiaomi 14 Pro',
        'wh1000xm5' => 'Sony WH-1000XM5',
        'xm5' => 'Sony WH-1000XM5',
        'xm4' => 'Sony WH-1000XM4',
    ];

    /**
     * Category colloquial terms & synonyms.
     */
    public const CATEGORY_SYNONYMS = [
        'tai nghe không dây' => 'tai nghe',
        'tai nghe ko day' => 'tai nghe',
        'tai nghe tws' => 'tai nghe',
        'earbuds' => 'tai nghe',
        'airpods' => 'tai nghe',
        'củ sạc' => 'sạc',
        'cu sac' => 'sạc',
        'cốc sạc' => 'sạc',
        'coc sac' => 'sạc',
        'cục sạc' => 'sạc',
        'cuc sac' => 'sạc',
        'adapter' => 'sạc',
        'dây sạc' => 'cáp sạc',
        'day sac' => 'cáp sạc',
        'dây cáp' => 'cáp sạc',
        'day cap' => 'cáp sạc',
        'kính cường lực' => 'kính cường lực nano',
        'kinh cuong luc' => 'kính cường lực nano',
        'miếng dán màn hình' => 'kính cường lực nano',
        'dán màn hình' => 'kính cường lực nano',
        'bao da' => 'ốp lưng',
        'case' => 'ốp lưng',
        'đồng hồ thông minh' => 'smartwatch',
        'dong ho thong minh' => 'smartwatch',
        'pin dự phòng' => 'sạc dự phòng',
        'sạc dự phòng' => 'sạc',
    ];

    /**
     * Common typing mistakes (Typo Tolerance).
     */
    public const TYPO_DICTIONARY = [
        'samsng' => 'samsung',
        'samssung' => 'samsung',
        'sansung' => 'samsung',
        'sumsung' => 'samsung',
        'iphnoe' => 'iphone',
        'iphoen' => 'iphone',
        'ipone' => 'iphone',
        'iphon' => 'iphone',
        'ifone' => 'iphone',
        'xaiomi' => 'xiaomi',
        'xiaomy' => 'xiaomi',
        'xiomi' => 'xiaomi',
        'xiao mi' => 'xiaomi',
        'opp' => 'oppo',
        'ankr' => 'anker',
        'anke' => 'anker',
        'snadragon' => 'snapdragon',
        'snapdrgon' => 'snapdragon',
        'blutooth' => 'bluetooth',
        'blooth' => 'bluetooth',
        'bluetoot' => 'bluetooth',
        'tainghe' => 'tai nghe',
        'dienthoai' => 'điện thoại',
        'sacnhanh' => 'sạc nhanh',
    ];

    /**
     * Resolve exact model alias if matched.
     */
    public static function resolveModelAlias(string $query): ?string
    {
        $clean = mb_strtolower(trim($query), 'UTF-8');

        return self::MODEL_ALIASES[$clean] ?? null;
    }

    /**
     * Replace model aliases in query (sorted longest first to avoid partial overlap).
     * Returns [$replacedText, $detectedModelAlias]
     */
    public static function replaceModelAliases(string $text): array
    {
        $aliases = self::MODEL_ALIASES;
        uksort($aliases, fn ($a, $b) => mb_strlen($b) <=> mb_strlen($a));

        $replaced = $text;
        $detectedModel = null;

        foreach ($aliases as $alias => $canonical) {
            $pattern = '/\b'.preg_quote($alias, '/').'\b/iu';
            if (preg_match($pattern, $replaced)) {
                if ($detectedModel === null) {
                    $detectedModel = $canonical;
                }
                $replaced = preg_replace($pattern, $canonical, $replaced);
                break;
            }
        }

        // Clean up any repeated brand names from replacement (e.g. "Samsung Samsung Galaxy")
        $replaced = preg_replace('/\b(samsung|apple|xiaomi|oppo|sony|anker|baseus)\s+\1\b/iu', '$1', $replaced);

        return [$replaced, $detectedModel];
    }

    /**
     * Replace known typos in user query.
     * Returns [$correctedText, $hasTypo]
     */
    public static function correctTypos(string $text): array
    {
        $words = preg_split('/\s+/', trim($text));
        $hasTypo = false;
        $correctedWords = [];

        foreach ($words as $w) {
            $lower = mb_strtolower($w, 'UTF-8');
            if (isset(self::TYPO_DICTIONARY[$lower])) {
                $correctedWords[] = self::TYPO_DICTIONARY[$lower];
                $hasTypo = true;
            } else {
                // Check Levenshtein distance for core brand typos (> 3 chars)
                $matchedFuzzy = false;
                if (mb_strlen($lower) >= 4) {
                    foreach (['samsung', 'iphone', 'xiaomi', 'anker', 'bluetooth', 'airpods'] as $target) {
                        $dist = levenshtein($lower, $target);
                        if ($dist === 1 || ($dist === 2 && mb_strlen($lower) >= 6)) {
                            $correctedWords[] = $target;
                            $hasTypo = true;
                            $matchedFuzzy = true;
                            break;
                        }
                    }
                }
                if (! $matchedFuzzy) {
                    $correctedWords[] = $w;
                }
            }
        }

        $correctedText = implode(' ', $correctedWords);
        $correctedText = preg_replace('/\b(samsung|apple|xiaomi|oppo|sony|anker|baseus)\s+\1\b/iu', '$1', $correctedText);

        return [$correctedText, $hasTypo];
    }

    /**
     * Normalize slang and colloquial terms.
     */
    public static function normalizeSlangAndCategories(string $text): string
    {
        $result = $text;

        // 1. Category synonyms replacement
        foreach (self::CATEGORY_SYNONYMS as $slang => $canonical) {
            $pattern = '/\b'.preg_quote($slang, '/').'\b/iu';
            if (preg_match($pattern, $result)) {
                $result = preg_replace($pattern, $canonical, $result);
            }
        }

        // 2. Brand slang replacement
        foreach (self::BRAND_ALIASES as $slang => $brand) {
            $pattern = '/\b'.preg_quote($slang, '/').'\b/iu';
            if (preg_match($pattern, $result)) {
                $result = preg_replace($pattern, $brand, $result);
            }
        }

        return $result;
    }
}
