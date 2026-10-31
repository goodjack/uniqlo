<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

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

    /**
     * @return array<string, mixed> 更新後的頁面（含新的 last_edited_time）
     */
    public function updatePage(string $pageId, array $properties): array
    {
        return $this->request()->patch("pages/{$pageId}", ['properties' => $properties])->json();
    }
}
