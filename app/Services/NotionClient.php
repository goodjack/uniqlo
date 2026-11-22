<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * 對 Notion 的讀寫都經過這裡；寫入只有 updatePage 與 trashPage 兩個，要試跑不送出時換掉這兩個就好（updatePage 要回傳套用後的頁面）。
 */
class NotionClient
{
    private function request(): PendingRequest
    {
        return Http::baseUrl('https://api.notion.com/v1/')
            ->withToken((string) config('services.notion.token'))
            ->withHeaders(['Notion-Version' => config('services.notion.version')])
            ->timeout(30)
            ->retry(2, 1000)
            ->throw();
    }

    /**
     * 讀出整個 data source。Notion 讀不到使用者手動拖曳的順序，回傳順序沒有意義。
     *
     * @return array<int, array<string, mixed>>
     */
    public function queryAll(string $dataSourceId): array
    {
        $pages = [];
        $cursor = null;

        do {
            $body = array_filter(['page_size' => 100, 'start_cursor' => $cursor]);
            $response = $this->request()->post("data_sources/{$dataSourceId}/query", $body)->json();
            array_push($pages, ...$response['results']);
            $cursor = $response['has_more'] ? $response['next_cursor'] : null;
        } while ($cursor);

        return $pages;
    }

    /** @return array<string, mixed> 單一頁面，跟清單裡讀到的一樣帶著欄位與 last_edited_time */
    public function getPage(string $pageId): array
    {
        return $this->request()->get("pages/{$pageId}")->json();
    }

    /**
     * 頁面內文最前面的幾個區塊。
     *
     * @return array{results: array<int, array<string, mixed>>, has_more: bool}
     */
    public function pageBlocks(string $pageId, int $limit): array
    {
        return $this->request()->get("blocks/{$pageId}/children", ['page_size' => $limit])->json();
    }

    /**
     * @return array<string, mixed> 更新後的頁面（含新的 last_edited_time）
     */
    public function updatePage(string $pageId, array $properties): array
    {
        return $this->request()->patch("pages/{$pageId}", ['properties' => $properties])->json();
    }

    /** 移到垃圾桶，30 天內可以從 Notion 還原。這版 API 只認 in_trash，不認舊的 archived */
    public function trashPage(string $pageId): void
    {
        $this->request()->patch("pages/{$pageId}", ['in_trash' => true]);
    }
}
