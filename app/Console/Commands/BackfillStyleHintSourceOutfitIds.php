<?php

namespace App\Console\Commands;

use App\Models\StyleHint;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class BackfillStyleHintSourceOutfitIds extends Command
{
    protected $signature = 'style-hint:backfill-source-outfit-id {--chunk=1000 : 每批處理幾筆}';

    protected $description = '補上舊穿搭的原始穿搭編號';

    /**
     * 只處理還沒有編號的穿搭，重跑會接著補；解析不出編號的維持空值。
     */
    public function handle(): int
    {
        $filled = 0;
        $unresolved = 0;

        StyleHint::query()
            ->select(['id', 'style_image_url', 'original_source_url'])
            ->whereNull('source_outfit_id')
            ->chunkById(max(1, (int) $this->option('chunk')), function (Collection $styleHints) use (&$filled, &$unresolved) {
                $sourceOutfitIds = $styleHints
                    ->mapWithKeys(fn (StyleHint $styleHint) => [$styleHint->id => $styleHint->resolveSourceOutfitId()])
                    ->filter();

                $unresolved += $styleHints->count() - $sourceOutfitIds->count();

                if ($sourceOutfitIds->isEmpty()) {
                    return;
                }

                $this->updateSourceOutfitIds($sourceOutfitIds);
                $filled += $sourceOutfitIds->count();

                $this->line("已補 {$filled} 筆，解析不出 {$unresolved} 筆");
            });

        $this->info("補完：{$filled} 筆有原始穿搭編號，{$unresolved} 筆解析不出（各自算一則）");

        return self::SUCCESS;
    }

    /**
     * 不更新 updated_at。
     *
     * @param  Collection<int, string>  $sourceOutfitIds  以穿搭 id 為鍵
     */
    private function updateSourceOutfitIds(Collection $sourceOutfitIds): void
    {
        $cases = $sourceOutfitIds->map(fn () => 'WHEN ? THEN ?')->implode(' ');
        $bindings = $sourceOutfitIds->flatMap(fn (string $sourceOutfitId, int $id) => [$id, $sourceOutfitId])->all();
        $placeholders = $sourceOutfitIds->map(fn () => '?')->implode(', ');

        DB::update(
            "UPDATE style_hints SET source_outfit_id = CASE id {$cases} END WHERE id IN ({$placeholders})",
            [...$bindings, ...$sourceOutfitIds->keys()->all()]
        );
    }
}
