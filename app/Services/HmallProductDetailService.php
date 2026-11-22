<?php

namespace App\Services;

use App\Models\HmallProduct;
use App\Models\HmallProductDetail;
use App\Presenters\HmallProductPresenter;
use App\Services\Traits\AntiBlockingCrawler;
use App\Support\OfficialProductDetail;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * 每件抓完就寫進資料庫，下一輪由資料庫的狀態決定該抓誰，中斷後重跑直接接著做。
 */
class HmallProductDetailService
{
    use AntiBlockingCrawler;

    public const OUTCOME_CHANGED = 'changed';

    public const OUTCOME_UNCHANGED = 'unchanged';

    public const OUTCOME_MISSING = 'missing';

    /** 上架幾天內算新商品 */
    private const NEW_PRODUCT_DAYS = 15;

    /** 失敗後最多隔幾天再試 */
    private const MAX_RETRY_DAYS = 16;

    /** @var array<int, string> */
    private array $pendingPurges = [];

    public function __construct(
        private readonly CloudflareCache $cloudflareCache,
        private readonly HmallProductPresenter $presenter,
    ) {}

    /**
     * @return array{checked: int, changed: int, missing: int, failed: int, stopped: string|null, blocked: bool}
     */
    public function fetchDue(string $brand, ?int $priorityLimit = null, ?int $rotationLimit = null): array
    {
        $config = config('app.crawler.product_details');

        return $this->fetchProducts($this->dueProducts(
            $brand,
            $priorityLimit ?? $config['priority_limit'],
            $rotationLimit ?? $config['rotation_limit'],
        ));
    }

    /**
     * @return Collection<int, HmallProduct>
     */
    public function dueProducts(string $brand, int $priorityLimit, int $rotationLimit): Collection
    {
        $now = now();
        $newSince = $now->copy()->subDays(self::NEW_PRODUCT_DAYS);
        $isDue = fn (Builder $query) => $query->whereNull('d.id')->orWhere('d.next_fetch_at', '<=', $now);
        $notWaitingToRetry = fn (Builder $query) => $query->whereNull('d.id')
            ->orWhere('d.failure_count', 0)
            ->orWhere('d.next_fetch_at', '<=', $now);

        $priority = $this->inStock($brand)
            ->where(fn (Builder $query) => $query
                ->whereNotNull('d.stale_since')
                ->orWhere(fn (Builder $query) => $query
                    ->where('hmall_products.created_at', '>=', $newSince)
                    ->where($isDue)))
            ->where($notWaitingToRetry)
            // 新商品沒抓過的最先，再來是新商品的追蹤抓取，清單有變動的排在後面，免得大量變動時把新商品擠掉
            ->orderByRaw('CASE WHEN d.id IS NULL THEN 0 WHEN hmall_products.created_at >= ? AND d.next_fetch_at <= ? THEN 1 ELSE 2 END', [$newSince, $now])
            ->orderByRaw('COALESCE(d.failure_count, 0)')
            ->orderByDesc('hmall_products.id')
            ->limit($priorityLimit)
            ->get();

        $rotation = $this->inStock($brand)
            ->whereNull('d.stale_since')
            ->where(fn (Builder $query) => $query
                ->where('hmall_products.created_at', '<', $newSince)
                ->orWhereNull('hmall_products.created_at'))
            ->where($isDue)
            ->where($notWaitingToRetry)
            ->orderByRaw('d.id IS NOT NULL')
            ->orderBy('d.next_fetch_at')
            ->orderByDesc('hmall_products.id')
            ->limit($rotationLimit)
            ->get();

        return $priority->concat($rotation)->load('detail');
    }

    /**
     * @param  iterable<int, HmallProduct>  $products
     * @return array{checked: int, changed: int, missing: int, failed: int, stopped: string|null, blocked: bool}
     */
    public function fetchProducts(iterable $products): array
    {
        $summary = ['checked' => 0, 'changed' => 0, 'missing' => 0, 'failed' => 0, 'stopped' => null, 'blocked' => false];
        $consecutiveFailures = 0;
        $maxConsecutiveFailures = (int) config('app.crawler.product_details.max_consecutive_failures');

        $this->resetDetailCounter();

        foreach ($products as $product) {
            try {
                $outcome = $this->fetch($product);
            } catch (Throwable $e) {
                $status = $e instanceof RequestException ? $e->response?->status() : null;

                Log::error('Fetching an official product detail failed', [
                    'brand' => $product->brand,
                    'product_code' => $product->product_code,
                    'status_code' => $status,
                    'error' => $e->getMessage(),
                ]);

                $summary['failed']++;
                $consecutiveFailures++;

                if ($this->isBlocked($e)) {
                    $summary['stopped'] = "官網擋下請求（{$status}）";
                    $summary['blocked'] = true;
                    break;
                }

                $this->recordFailure($product);

                if ($consecutiveFailures >= $maxConsecutiveFailures) {
                    $summary['stopped'] = "連續 {$consecutiveFailures} 件抓取失敗";
                    break;
                }

                $this->randomDelay();

                continue;
            }

            $consecutiveFailures = 0;
            $summary['checked']++;
            $summary['changed'] += $outcome === self::OUTCOME_CHANGED ? 1 : 0;
            $summary['missing'] += $outcome === self::OUTCOME_MISSING ? 1 : 0;

            $this->detailCounter++;

            if ($this->shouldDetailBatchRest()) {
                $this->doDetailBatchRest();
            }

            $this->randomDelay();
        }

        $this->flushPurges();

        Log::info('Fetched official product details', $summary);

        return $summary;
    }

    /**
     * 內容沒變時只記時間。
     */
    public function fetch(HmallProduct $product): string
    {
        $response = $this->get($this->detailApiUrl($product->brand), [
            'productCode' => $product->product_code,
            'distribution' => 'EXPRESS',
            'type' => 'DETAIL',
        ]);

        // 回的不是 JSON 當成失敗；JSON 說找不到才是真的沒有
        if ($response !== null && ! is_array($response->json())) {
            throw new RuntimeException('The product detail response is not JSON');
        }

        $official = $response === null ? null : OfficialProductDetail::fromResponse($response->json());

        if ($official === null) {
            $detail = $product->detail ?? new HmallProductDetail;
            $this->saveFetchTime($product, $detail, $detail->validators ?? []);

            Log::info('The official site has no detail for this product', [
                'brand' => $product->brand,
                'product_code' => $product->product_code,
            ]);

            return self::OUTCOME_MISSING;
        }

        $detail = $product->detail ?? new HmallProductDetail;
        $isFirstFetch = ! $detail->exists;
        $fragments = $this->fetchFragments($product, $detail, $official->fragmentPaths);

        $instruction = $this->keptInstruction($product, $fragments['instruction']['body']);
        $sizeChart = $this->refreshedSizeChart($product, $official->sizeChartPath);

        $content = [
            'main_images' => $official->mainImages,
            'videos' => $official->videos,
            'rating_levels' => $official->ratingLevels,
            'origin_place' => $official->originPlace,
            'features' => $fragments['features']['value'],
            'material_care' => $fragments['material_care']['value'],
        ];

        $fingerprint = $this->fingerprint($content + ['description' => $this->presenter->getDescriptionBody($instruction)]);

        $isChanged = $isFirstFetch || $detail->fingerprint !== $fingerprint;
        $validators = array_filter(array_map(fn (array $fragment) => $fragment['validator'], $fragments));

        DB::transaction(function () use ($product, $detail, $content, $fingerprint, $isChanged, $instruction, $sizeChart, $validators) {
            if ($isChanged) {
                $detail->fill($content);
                $detail->fingerprint = $fingerprint;
                $detail->content_changed_at = now();
            }

            if ($instruction !== $product->instruction || $sizeChart !== $product->size_chart) {
                $product->instruction = $instruction;
                $product->size_chart = $sizeChart;
                $product->timestamps = false;
                $product->save();
                $product->timestamps = true;
            }

            $this->saveFetchTime($product, $detail, $validators);
        });

        if ($isChanged) {
            $this->queuePurge($product->route_url);
        }

        return $isChanged ? self::OUTCOME_CHANGED : self::OUTCOME_UNCHANGED;
    }

    public function nextFetchAt(HmallProduct $product, CarbonInterface $fetchedAt): CarbonInterface
    {
        $config = config('app.crawler.product_details');

        // 上架時間不明就不算新商品，直接進輪替；拿這次的時間去算會每次都排到第一個追蹤日
        foreach ($product->created_at === null ? [] : $config['follow_up_days'] as $days) {
            $followUpAt = $product->created_at->copy()->addDays($days);

            if ($followUpAt->greaterThan($fetchedAt)) {
                return $followUpAt;
            }
        }

        return $fetchedAt->copy()->addDays(random_int($config['rotation_days']['min'], $config['rotation_days']['max']));
    }

    /**
     * 鍵先排序：資料庫的 JSON 欄位存回來會重排物件的鍵，順序不同不能算內容變了。
     */
    private function fingerprint(array $content): string
    {
        $canonical = function (mixed $value) use (&$canonical) {
            if (! is_array($value)) {
                return $value;
            }

            if (! array_is_list($value)) {
                ksort($value);
            }

            return array_map($canonical, $value);
        };

        return sha1(json_encode($canonical($content), JSON_UNESCAPED_UNICODE));
    }

    private function inStock(string $brand): Builder
    {
        return HmallProduct::query()
            ->leftJoin('hmall_product_details as d', 'd.hmall_product_id', '=', 'hmall_products.id')
            ->where('hmall_products.brand', $brand)
            ->whereNull('hmall_products.stockout_at')
            ->select('hmall_products.*');
    }

    /**
     * @param  array<string, string|null>  $paths
     * @return array<string, array{body: string|null, value: array|null, validator: array|null}>
     */
    private function fetchFragments(HmallProduct $product, HmallProductDetail $detail, array $paths): array
    {
        $stored = [
            OfficialProductDetail::FRAGMENT_FEATURES => $detail->features,
            OfficialProductDetail::FRAGMENT_MATERIAL_CARE => $detail->material_care,
        ];
        $parsers = [
            OfficialProductDetail::FRAGMENT_FEATURES => OfficialProductDetail::parseFeatures(...),
            OfficialProductDetail::FRAGMENT_MATERIAL_CARE => OfficialProductDetail::parseMaterialCare(...),
        ];

        $fragments = [];

        foreach ($paths as $name => $path) {
            $fragments[$name] = ['body' => null, 'value' => null, 'validator' => null];

            if ($path === null) {
                continue;
            }

            $this->randomDelay();

            $validator = $detail->validators[$name] ?? null;
            $response = $this->get(OfficialProductDetail::url($product->brand, $path), [], array_filter([
                'If-None-Match' => $validator['etag'] ?? null,
                'If-Modified-Since' => $validator['last_modified'] ?? null,
            ]));

            // 詳細資料還列著這個片段、片段卻找不到，多半是官網暫時的狀況，跟空內容一樣保留已存的
            if ($response === null) {
                $fragments[$name] = ['body' => null, 'value' => $stored[$name] ?? null, 'validator' => $validator];

                continue;
            }

            if ($response->status() === 304) {
                $fragments[$name] = [
                    'body' => $name === OfficialProductDetail::FRAGMENT_INSTRUCTION ? $product->instruction : null,
                    'value' => $stored[$name] ?? null,
                    'validator' => $validator,
                ];

                continue;
            }

            $body = $response->body();
            // 跟說明一樣，暫時拿到空的就保留已存的內容
            $value = isset($parsers[$name]) ? ($parsers[$name]($body) ?: ($stored[$name] ?? null)) : null;

            $fragments[$name] = [
                'body' => $body,
                'value' => $value,
                'validator' => array_filter([
                    'etag' => $response->header('ETag') ?: null,
                    'last_modified' => $response->header('Last-Modified') ?: null,
                ]) ?: null,
            ];
        }

        return $fragments;
    }

    /**
     * 說明原本還是空殼的商品（多半是剛上架），連尺寸表一起補抓；抓到空的就保留原本的。
     */
    private function refreshedSizeChart(HmallProduct $product, ?string $path): ?string
    {
        if ($path === null || $this->presenter->getDescriptionBody($product->instruction) !== '') {
            return $product->size_chart;
        }

        $this->randomDelay();
        $response = $this->get(OfficialProductDetail::url($product->brand, $path));
        $body = $response?->body();

        return $body !== null && trim(strip_tags($body)) !== '' ? $body : $product->size_chart;
    }

    /**
     * 官網偶爾暫時把說明換成空殼，那時不蓋掉已存的說明。
     */
    private function keptInstruction(HmallProduct $product, ?string $fetched): ?string
    {
        if ($fetched === null || $this->presenter->getDescriptionBody($fetched) === '') {
            return $product->instruction ?? $fetched;
        }

        if ($this->presenter->getDescriptionBody($fetched) === $this->presenter->getDescriptionBody($product->instruction)) {
            return $product->instruction;
        }

        return $fetched;
    }

    /**
     * @param  array<string, array>  $validators
     */
    private function saveFetchTime(HmallProduct $product, HmallProductDetail $detail, array $validators): void
    {
        $now = now();

        $detail->hmall_product_id = $product->id;
        $detail->validators = $validators ?: null;
        $detail->fetched_at = $now;
        $detail->next_fetch_at = $this->nextFetchAt($product, $now);
        $detail->stale_since = null;
        $detail->failure_count = 0;
        $detail->save();

        $product->setRelation('detail', $detail);
    }

    /**
     * 找不到回傳 null。
     *
     * @param  array<string, string>  $query
     * @param  array<string, string>  $headers
     */
    private function get(string $url, array $query = [], array $headers = []): ?Response
    {
        try {
            return retry(
                config('app.crawler.retry.times'),
                fn () => Http::withHeaders(array_merge($this->buildHeaders(), $headers))->throw()->get($url, $query),
                fn ($attempts, $e) => $this->getRetrySleepMilliseconds($attempts, $e),
                fn ($e) => ! $this->isBlocked($e) && ! $this->isNotFound($e),
            );
        } catch (RequestException $e) {
            if ($this->isNotFound($e)) {
                return null;
            }

            throw $e;
        }
    }

    private function isBlocked(Throwable $e): bool
    {
        return $e instanceof RequestException && in_array($e->response?->status(), [403, 429], true);
    }

    private function isNotFound(Throwable $e): bool
    {
        return $e instanceof RequestException && $e->response?->status() === 404;
    }

    /**
     * 失敗的商品往後排，間隔逐次加倍，下一輪先抓別件。
     */
    private function recordFailure(HmallProduct $product): void
    {
        try {
            $detail = $product->detail ?? new HmallProductDetail;
            $detail->hmall_product_id = $product->id;
            $detail->failure_count = min(($detail->failure_count ?? 0) + 1, 255);
            $detail->last_failed_at = now();
            $detail->next_fetch_at = now()->addDays(min(2 ** ($detail->failure_count - 1), self::MAX_RETRY_DAYS));
            $detail->save();

            $product->setRelation('detail', $detail);
        } catch (Throwable $e) {
            report($e);
        }
    }

    private function queuePurge(string $url): void
    {
        $this->pendingPurges[] = $url;

        if (count($this->pendingPurges) >= CloudflareCache::MAX_URLS_PER_REQUEST) {
            $this->flushPurges();
        }
    }

    /**
     * 頁面快取本身很快就過期，清不掉只記 log（CloudflareCache 會記），不留到下一輪。
     */
    private function flushPurges(): void
    {
        if ($this->pendingPurges !== []) {
            $this->cloudflareCache->purgeUrls($this->pendingPurges);
            $this->pendingPurges = [];
        }
    }

    private function detailApiUrl(string $brand): string
    {
        return config($brand === 'GU' ? 'gu.api.v3.detail.tw' : 'uniqlo.api.v3.detail.tw');
    }
}
