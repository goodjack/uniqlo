<?php

namespace App\Services;

use App\Enums\BarcodeStatus;
use App\Enums\Brand;
use App\Exceptions\BarcodeLookupUnavailableException;
use App\Models\Barcode;
use App\Repositories\BarcodeRepository;
use App\Repositories\HmallProductRepository;
use App\Support\BarcodeResult;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * 用吊牌條碼找商品：先查自己存的，沒有才依序問官網，查到就存起來。
 *
 * 查到的永久保存，同一個條碼不必再問官網；查不到的只放快取
 * 1 天（新品可能明天就查得到），否則同一個無效條碼每掃一次就要問四次，重複掃
 * 或被程式打會吃光每日上限，正常使用者跟著看到「暫時無法查詢」。
 */
class BarcodeService extends Service
{
    public const NOT_FOUND_CACHE_PREFIX = 'barcode:not_found:';

    public const NOT_FOUND_TTL_SECONDS = 86400;

    public const UPSTREAM_COUNTER_PREFIX = 'barcode:upstream:';

    public const UPSTREAM_LOCK = 'barcode:upstream-lock';

    /** 等別的請求數完的最長秒數，等不到就當成暫時無法查詢 */
    public const UPSTREAM_LOCK_WAIT_SECONDS = 2;

    private const JAPAN_NOT_FOUND_CODE = 33;

    /** 日本版資訊那一段的錨點，見 hmall-products/show.blade.php */
    public const JAPAN_SECTION_ANCHOR = 'japan';

    /** @var BarcodeRepository */
    protected $repository;

    private HmallProductRepository $hmallProductRepository;

    public function __construct(BarcodeRepository $repository, HmallProductRepository $hmallProductRepository)
    {
        $this->repository = $repository;
        $this->hmallProductRepository = $hmallProductRepository;
    }

    /**
     * @param  string  $client  呼叫端的 IP，用來算每個來源每天能讓網站問官網幾次
     */
    public function lookup(string $barcode, string $client): BarcodeResult
    {
        if (! self::isValidEan13($barcode)) {
            return new BarcodeResult(BarcodeStatus::Invalid, $barcode);
        }

        // 檢查碼錯的、不是店內碼的都不問官網，也不進快取
        if (! self::isInStoreCode($barcode)) {
            return new BarcodeResult(BarcodeStatus::NotProduct, $barcode);
        }

        $record = $this->repository->findByBarcode($barcode);

        if ($record) {
            return $this->resolve($record);
        }

        if (Cache::has(self::NOT_FOUND_CACHE_PREFIX.$barcode)) {
            return new BarcodeResult(BarcodeStatus::NotProduct, $barcode);
        }

        try {
            $attributes = $this->askOfficialSites($barcode, $client);
        } catch (BarcodeLookupUnavailableException $e) {
            Log::warning('條碼查詢暫時無法使用', ['reason' => $e->getMessage()]);

            return new BarcodeResult(BarcodeStatus::Unavailable, $barcode);
        }

        if ($attributes === null) {
            Cache::put(self::NOT_FOUND_CACHE_PREFIX.$barcode, true, self::NOT_FOUND_TTL_SECONDS);

            return new BarcodeResult(BarcodeStatus::NotProduct, $barcode);
        }

        return $this->resolve($this->repository->save($attributes));
    }

    public static function isValidEan13(string $barcode): bool
    {
        if (! preg_match('/^\d{13}$/', $barcode)) {
            return false;
        }

        $sum = 0;

        for ($i = 0; $i < 12; $i++) {
            $sum += (int) $barcode[$i] * ($i % 2 === 0 ? 1 : 3);
        }

        return (10 - $sum % 10) % 10 === (int) $barcode[12];
    }

    /**
     * GS1 把 20 到 29 開頭留給店內自用。一般商品條碼（台灣 471、日本 45、49）
     * 不會是 UNIQLO 或 GU 的吊牌；之後若發現例外再放寬。
     */
    public static function isInStoreCode(string $barcode): bool
    {
        return $barcode[0] === '2';
    }

    /**
     * @return array<string, mixed>|null 四家都查不到時是 null
     *
     * @throws BarcodeLookupUnavailableException
     */
    private function askOfficialSites(string $barcode, string $client): ?array
    {
        $asked = false;

        foreach ($this->sources() as [$region, $brand, $urlTemplate]) {
            if (empty($urlTemplate)) {
                continue;
            }

            $asked = true;
            $json = $this->request($region, $brand, str_replace('{barcode}', $barcode, $urlTemplate), $client);
            $found = $region === Barcode::REGION_JP
                ? $this->parseJapan($json, $brand)
                : $this->parseTaiwan($json, $brand);

            if ($found !== null) {
                return $found + [
                    'barcode' => $barcode,
                    'region' => $region,
                    'brand' => $brand,
                ];
            }
        }

        if (! $asked) {
            throw new BarcodeLookupUnavailableException('沒有設定查詢網址');
        }

        return null;
    }

    /**
     * @return array<int, array{0: string, 1: Brand, 2: string|null}>
     */
    private function sources(): array
    {
        return [
            [Barcode::REGION_TW, Brand::Uniqlo, config('uniqlo.api.sku_lookup.tw')],
            [Barcode::REGION_TW, Brand::Gu, config('gu.api.sku_lookup.tw')],
            [Barcode::REGION_JP, Brand::Uniqlo, config('uniqlo.api.sku_lookup.jp')],
            [Barcode::REGION_JP, Brand::Gu, config('gu.api.sku_lookup.jp')],
        ];
    }

    /**
     * @throws BarcodeLookupUnavailableException
     */
    private function request(string $region, Brand $brand, string $url, string $client): array
    {
        if (! $this->takeUpstreamQuota($client)) {
            throw new BarcodeLookupUnavailableException('今天的查詢次數已用完');
        }

        try {
            $response = Http::withHeaders($this->headers($region))
                ->connectTimeout(3)
                ->timeout(6)
                ->get($url);
        } catch (ConnectionException $e) {
            throw new BarcodeLookupUnavailableException("{$region} {$brand->value} 連線失敗：{$e->getMessage()}");
        }

        $json = $response->successful() ? $response->json() : null;

        if (! is_array($json)) {
            throw new BarcodeLookupUnavailableException("{$region} {$brand->value} 回應異常：HTTP {$response->status()}");
        }

        return $json;
    }

    /**
     * 每天向官網送出的請求數，全站一份、每個來源一份，每一次都算，不論查到與否；
     * 日期放進 key，過了午夜自然換一組。只有單一來源的上限，一個人不到一小時就能
     * 用完全站額度，讓其他人都變成暫時無法查詢。
     *
     * 讀、比、加一要在同一把鎖裡做：網站快取是檔案，increment 不是原子的，
     * 同時進來的請求會讀到同一個數字而一起超過上限。兩個都還有額度才一起加一，
     * 被個人上限擋下的不會吃到全站額度。
     */
    private function takeUpstreamQuota(string $client): bool
    {
        $date = now()->toDateString();
        $globalKey = self::UPSTREAM_COUNTER_PREFIX.$date;
        $clientKey = self::UPSTREAM_COUNTER_PREFIX.$date.':'.sha1($client);
        $globalMax = (int) config('barcode.limits.daily_upstream');
        $clientMax = (int) config('barcode.limits.daily_upstream_per_client');

        try {
            return Cache::lock(self::UPSTREAM_LOCK, 10)->block(
                self::UPSTREAM_LOCK_WAIT_SECONDS,
                function () use ($globalKey, $clientKey, $globalMax, $clientMax) {
                    $global = (int) Cache::get($globalKey, 0);
                    $mine = (int) Cache::get($clientKey, 0);

                    if ($global >= $globalMax || $mine >= $clientMax) {
                        return false;
                    }

                    Cache::put($globalKey, $global + 1, now()->addDay());
                    Cache::put($clientKey, $mine + 1, now()->addDay());

                    return true;
                },
            );
        } catch (LockTimeoutException) {
            return false;
        }
    }

    /**
     * @return array<string, string>
     */
    private function headers(string $region): array
    {
        $headers = json_decode((string) config("barcode.headers.{$region}"), true);

        if (! is_array($headers)) {
            return [];
        }

        return array_filter(
            $headers,
            fn ($value, $name) => is_string($name) && is_scalar($value),
            ARRAY_FILTER_USE_BOTH,
        );
    }

    /**
     * 只有 success 是 true、resp[0] 在、spu 明確是 null 才算查不到；其他長相都可能是
     * 官網出錯或改版，不能當成查不到放進快取。
     *
     * @throws BarcodeLookupUnavailableException
     */
    private function parseTaiwan(array $json, Brand $brand): ?array
    {
        $item = $json['resp'][0] ?? null;

        if (($json['success'] ?? null) !== true || ! is_array($item) || ! array_key_exists('spu', $item)) {
            throw new BarcodeLookupUnavailableException("tw {$brand->value} 回應格式不對");
        }

        if ($item['spu'] === null) {
            return null;
        }

        if (! is_string($item['spu']) || $item['spu'] === '') {
            throw new BarcodeLookupUnavailableException("tw {$brand->value} 回應格式不對");
        }

        // goods_num 是 482306000 這種，前 6 碼是吊牌編號
        $goodsNum = (string) ($item['goods_num'] ?? '');

        return [
            'product_code' => $item['spu'],
            'codes' => preg_match('/^\d{6}/', $goodsNum) ? [substr($goodsNum, 0, 6)] : [],
        ];
    }

    /**
     * 查不到時 HTTP 仍是 200，內文是 status nok 加 error.code 33；其他 nok 是別的錯誤，
     * 不能當成查不到。
     *
     * @throws BarcodeLookupUnavailableException
     */
    private function parseJapan(array $json, Brand $brand): ?array
    {
        $status = $json['status'] ?? null;

        if ($status === 'nok' && ($json['error']['code'] ?? null) === self::JAPAN_NOT_FOUND_CODE) {
            return null;
        }

        $result = $json['result'] ?? null;

        if ($status !== 'ok' || ! is_array($result) || empty($result['productId']) || ! is_string($result['productId'])) {
            throw new BarcodeLookupUnavailableException("jp {$brand->value} 回應格式不對");
        }

        $codes = array_values(array_filter(
            (array) ($result['l1Ids'] ?? []),
            fn ($code) => is_string($code) && preg_match('/^\d{6}$/', $code),
        ));

        return [
            'product_code' => $result['productId'],
            'codes' => $codes,
        ];
    }

    /**
     * 掃的當下才對到網站商品頁：網站的商品會上架、下架，存的只有官網那一側的事實。
     */
    private function resolve(Barcode $record): BarcodeResult
    {
        $codes = $record->codes ?? [];

        if ($record->region === Barcode::REGION_TW) {
            $product = $this->hmallProductRepository->findByBrandAndProductCode($record->brand, $record->product_code);

            if ($product) {
                return new BarcodeResult(BarcodeStatus::Found, $record->barcode, $codes[0] ?? $product->code, $product->route_url, $record->brand);
            }
        } else {
            // 日本吊牌對到台灣同編號的商品，直接捲到日本版資訊；那一段只在有日本資料時才有
            foreach ($codes as $code) {
                $product = $this->hmallProductRepository->findPreferredByBrandAndCode($record->brand, $code);

                if ($product) {
                    $anchor = $product->japanProduct ? '#'.self::JAPAN_SECTION_ANCHOR : '';

                    return new BarcodeResult(BarcodeStatus::Found, $record->barcode, $code, $product->route_url.$anchor, $record->brand);
                }
            }
        }

        return new BarcodeResult(BarcodeStatus::NotListed, $record->barcode, $codes[0] ?? null, $record->official_url, $record->brand);
    }
}
