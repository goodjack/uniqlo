<?php

namespace Tests\Unit\Repositories;

use App\Models\StyleHint;
use App\Models\StyleHintItem;
use App\Repositories\StyleHintRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Tests\TestCase;

class StyleHintRepositoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_ugc_content_without_an_id_is_skipped_and_the_rest_of_the_page_is_saved(): void
    {
        Log::shouldReceive('error')->once();

        app(StyleHintRepository::class)->saveStyleHintsFromUgc([
            $this->ugcContent('100', '471234-09'),
            (object) ['image_original' => 'no-id.jpg'],
            $this->ugcContent('300', '475678-01'),
        ]);

        $this->assertSame(['100', '300'], StyleHint::orderBy('outfit_id')->pluck('outfit_id')->all());
        $this->assertSame(['471234', '475678'], StyleHintItem::orderBy('code')->pluck('code')->all());
    }

    public function test_ugc_content_that_fails_to_save_is_skipped_and_the_rest_of_the_page_is_saved(): void
    {
        Log::shouldReceive('error');
        StyleHint::creating(function (StyleHint $styleHint) {
            if ($styleHint->outfit_id === '200') {
                throw new RuntimeException('Temporary database failure');
            }
        });

        app(StyleHintRepository::class)->saveStyleHintsFromUgc([
            $this->ugcContent('100', '471234-09'),
            $this->ugcContent('200', '472222-01'),
            $this->ugcContent('300', '475678-01'),
        ]);

        $this->assertSame(['100', '300'], StyleHint::orderBy('outfit_id')->pluck('outfit_id')->all());
        $this->assertSame(['471234', '475678'], StyleHintItem::orderBy('code')->pluck('code')->all());
    }

    private function ugcContent(string $id, string $productId): object
    {
        return json_decode(json_encode([
            'id_ugc_dist_content' => $id,
            'image_original' => "{$id}.jpg",
            'products' => [['ugc_item' => ['product_id' => $productId]]],
        ]));
    }
}
