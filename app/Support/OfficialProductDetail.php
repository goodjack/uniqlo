<?php

namespace App\Support;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;

/**
 * 只收官網自己的路徑，其他網址一律丟掉，頁面上才不會被塞進外站資源。
 */
final class OfficialProductDetail
{
    public const FRAGMENT_INSTRUCTION = 'instruction';

    public const FRAGMENT_FEATURES = 'features';

    public const FRAGMENT_MATERIAL_CARE = 'material_care';

    private const FRAGMENT_KEYS = [
        self::FRAGMENT_INSTRUCTION => 'instructionH5',
        self::FRAGMENT_FEATURES => 'features',
        self::FRAGMENT_MATERIAL_CARE => 'materialCare',
    ];

    private const MAX_FEATURES = 20;

    /**
     * @param  array<int, array{medium: string, large: string}>  $mainImages
     * @param  array<int, string>  $videos
     * @param  array<int, int>|null  $ratingLevels  五星到一星
     * @param  array<string, string|null>  $fragmentPaths
     * @param  string|null  $sizeChartPath  尺寸表，只在說明還是空殼時才補抓
     */
    public function __construct(
        public readonly array $mainImages,
        public readonly array $videos,
        public readonly ?array $ratingLevels,
        public readonly ?string $originPlace,
        public readonly array $fragmentPaths,
        public readonly ?string $sizeChartPath = null,
    ) {}

    /**
     * 官網沒有這件商品時回傳 null。
     */
    public static function fromResponse(mixed $body): ?self
    {
        if (! is_array($body) || ($body['success'] ?? false) !== true) {
            return null;
        }

        $product = $body['resp'][0] ?? null;

        if (! is_array($product)) {
            return null;
        }

        $main = data_get($product, 'imageInfo.main');
        $video = data_get($product, 'imageInfo.video');
        $summary = data_get($product, 'spuInfo.summary', []);
        $desc = data_get($product, 'spuInfo.desc', []);

        return new self(
            mainImages: self::mainImages(is_array($main) ? $main : []),
            videos: self::assetPaths(is_array($video) ? ($video['1000'] ?? []) : []),
            ratingLevels: self::ratingLevels($summary['levels'] ?? null),
            originPlace: self::text($summary['originPlaceSummary'] ?? null, 255),
            fragmentPaths: array_map(
                fn (string $key) => self::assetPath($desc[$key] ?? null),
                self::FRAGMENT_KEYS,
            ),
            sizeChartPath: self::assetPath($desc['sizeAndTryOnH5'] ?? null),
        );
    }

    /**
     * 官網給的站內路徑接上台灣站，已經是完整網址的照用。
     */
    public static function url(string $brand, string $path): string
    {
        if (str_starts_with($path, 'https://')) {
            return $path;
        }

        $host = $brand === 'GU' ? 'https://www.gu-global.com' : 'https://www.uniqlo.com';

        return "{$host}/tw{$path}";
    }

    /**
     * @return array<int, array{image: string|null, text: string}>
     */
    public static function parseFeatures(string $html): array
    {
        $xpath = self::xpath($html);

        if ($xpath === null) {
            return [];
        }

        $features = [];

        foreach ($xpath->query(self::classSelector('feature-item')) as $item) {
            $image = $xpath->query('.//img/@src', $item)->item(0)?->nodeValue;
            $desc = $xpath->query('.'.self::classSelector('feature-item-desc'), $item)->item(0);
            $text = self::text($desc?->textContent) ?? '';
            $image = self::assetPath($image);

            if ($image === null && $text === '') {
                continue;
            }

            $features[] = ['image' => $image, 'text' => $text];

            if (count($features) >= self::MAX_FEATURES) {
                break;
            }
        }

        return $features;
    }

    /**
     * 兩個品牌的寫法不同，都當成「標題後面接內文，到下一個標題為止」。
     *
     * @return array<int, array{title: string, content: string}>
     */
    public static function parseMaterialCare(string $html): array
    {
        $xpath = self::xpath($html);
        $body = $xpath?->query('//body')->item(0);

        if ($body === null) {
            return [];
        }

        $sections = [['title' => '', 'content' => '']];
        self::collectSections($body, $sections);

        return array_values(array_filter(array_map(function (array $section) {
            $lines = array_filter(array_map(fn (string $line) => self::text($line) ?? '', explode("\n", $section['content'])));

            return [
                'title' => rtrim($section['title'], ':：'),
                'content' => implode("\n", $lines),
            ];
        }, $sections), fn (array $section) => $section['title'] !== '' || $section['content'] !== ''));
    }

    /**
     * @param  array<int, array{title: string, content: string}>  $sections
     */
    private static function collectSections(DOMNode $node, array &$sections): void
    {
        foreach ($node->childNodes as $child) {
            if ($child->nodeType === XML_TEXT_NODE) {
                $sections[array_key_last($sections)]['content'] .= $child->nodeValue;

                continue;
            }

            if (! $child instanceof DOMElement || in_array($child->tagName, ['script', 'style'], true)) {
                continue;
            }

            if (self::isTitle($child)) {
                $sections[] = ['title' => self::text($child->textContent) ?? '', 'content' => ''];

                continue;
            }

            $isBreak = in_array($child->tagName, ['br', 'p', 'div', 'li'], true);

            if ($isBreak) {
                $sections[array_key_last($sections)]['content'] .= "\n";
            }

            self::collectSections($child, $sections);

            if ($isBreak) {
                $sections[array_key_last($sections)]['content'] .= "\n";
            }
        }
    }

    private static function isTitle(DOMElement $element): bool
    {
        return in_array($element->tagName, ['strong', 'b', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6'], true)
            || in_array('desc-title', preg_split('/\s+/', $element->getAttribute('class')), true);
    }

    /**
     * @param  array<string, mixed>  $main
     * @return array<int, array{medium: string, large: string}>
     */
    private static function mainImages(array $main): array
    {
        $large = self::assetPaths($main['1000'] ?? []);
        $medium = self::assetPaths($main['561'] ?? []);

        if ($large === []) {
            $large = $medium;
        }

        return array_map(fn (string $path, int $index) => [
            'medium' => count($medium) === count($large) ? $medium[$index] : $path,
            'large' => $path,
        ], $large, array_keys($large));
    }

    /**
     * @return array<int, int>|null
     */
    private static function ratingLevels(mixed $levels): ?array
    {
        if (! is_array($levels) || count($levels) !== 5) {
            return null;
        }

        foreach ($levels as $count) {
            if (! is_int($count) && ! (is_string($count) && ctype_digit($count))) {
                return null;
            }
        }

        return array_map('intval', array_values($levels));
    }

    /**
     * @return array<int, string>
     */
    private static function assetPaths(mixed $paths): array
    {
        if (! is_array($paths)) {
            return [];
        }

        return array_values(array_filter(array_map(self::assetPath(...), $paths)));
    }

    private static function assetPath(mixed $path): ?string
    {
        if (! is_string($path)) {
            return null;
        }

        $path = trim($path);

        if (preg_match('#^/[^/\\\\]#', $path) || preg_match('#^https://www\.(uniqlo|gu-global)\.com/#', $path)) {
            return $path;
        }

        return null;
    }

    private static function text(mixed $value, ?int $maxLength = null): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        // 零寬空白 trim 清不掉
        $text = trim(preg_replace('/[\s\x{200B}\x{FEFF}]+/u', ' ', $value) ?? '');

        if ($text === '') {
            return null;
        }

        return $maxLength === null ? $text : mb_substr($text, 0, $maxLength);
    }

    private static function xpath(string $html): ?DOMXPath
    {
        if (trim($html) === '') {
            return null;
        }

        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $loaded = $document->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return $loaded ? new DOMXPath($document) : null;
    }

    private static function classSelector(string $class): string
    {
        return "//*[contains(concat(' ', normalize-space(@class), ' '), ' {$class} ')]";
    }
}
