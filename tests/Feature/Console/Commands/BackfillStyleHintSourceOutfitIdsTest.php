<?php

namespace Tests\Feature\Console\Commands;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BackfillStyleHintSourceOutfitIdsTest extends TestCase
{
    use RefreshDatabase;

    private const STORED_ROWS = [
        // id => [資料庫裡存的 style_image_url, original_source_url, 應補上的原始穿搭編號]
        1 => ['jp991231', '2043193', '2043193'],
        2 => ['jp991231gu', '2043194', '2043194'],
        3 => ['us991231Igbiz555', 'https://www.instagram.com/p/C/', 'instagram-business:555'],
        4 => ['us991231Olapic666', 'https://www.instagram.com/p/C/', 'olapic:666'],
        5 => ['https://example.com/photo.jpg', 'https://example.com/post', null],
    ];

    public function test_it_fills_source_outfit_ids_from_the_stored_image_urls_without_touching_timestamps(): void
    {
        Http::preventStrayRequests();
        $this->seedStoredRows();

        $this->artisan('style-hint:backfill-source-outfit-id', ['--chunk' => 2])
            ->expectsOutputToContain('4 筆有原始穿搭編號，1 筆解析不出')
            ->assertSuccessful();

        $this->assertSame(
            array_map(fn (array $row) => $row[2], self::STORED_ROWS),
            DB::table('style_hints')->orderBy('id')->pluck('source_outfit_id', 'id')->all()
        );
        $this->assertSame(5, DB::table('style_hints')->where('updated_at', '2026-01-01 00:00:00')->count());
    }

    public function test_a_rerun_only_looks_at_rows_still_missing_the_id(): void
    {
        $this->seedStoredRows();
        DB::table('style_hints')->where('id', 1)->update(['source_outfit_id' => 'kept']);

        $this->artisan('style-hint:backfill-source-outfit-id')
            ->expectsOutputToContain('3 筆有原始穿搭編號，1 筆解析不出')
            ->assertSuccessful();

        $this->assertSame('kept', DB::table('style_hints')->where('id', 1)->value('source_outfit_id'));
    }

    private function seedStoredRows(): void
    {
        foreach (self::STORED_ROWS as $id => [$styleImageUrl, $originalSourceUrl]) {
            DB::table('style_hints')->insert([
                'id' => $id,
                'outfit_id' => "outfit-{$id}",
                'country' => 'jp',
                'style_image_url' => $styleImageUrl,
                'original_source_url' => $originalSourceUrl,
                'updated_at' => '2026-01-01 00:00:00',
            ]);
        }
    }
}
