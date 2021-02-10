<?php

namespace Tests\Unit\Services;

use App\Foundations\DivideProducts;
use App\Models\Product;
use App\Services\SearchService;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use PHPUnit\Framework\TestCase;

class SearchServiceTest extends TestCase
{
    use divideProducts;
    use RefreshDatabase;

    public function test_getSearchResults()
    {
        $this->mock(Client::class, function ($mock) {
            $mock->shouldReceive('request')
                ->once()
                ->andReturn(new Response(
                    $status = 200,
                    $headers = [],
                    File::get(base_path('tests/stubs/search-response.json'))
                ));
        });

        $service = app(SearchService::class);
        $results = $service->getSearchResults('query-string');

        $this->assertJsonStringEqualsJsonFile(
            base_path('tests/stubs/search-response.json'),
            json_encode($results)
        );
    }

    /**
     * @dataProvider searchResultsProvider
     */
    public function test_getProducts($searchResults)
    {
        Product::factory()->create([
            'id' => 409325,
        ]);

        Product::factory()->create([
            'id' => 40932500001,
        ]);

        Product::factory()->count(10)->create();

        $service = app(SearchService::class);
        $products = $service->getProducts($searchResults);

        $expected = $this->divideProducts(
            Product::where('id', 409325)
            ->orWhere('id', 40932500001)
            ->get()
        );

        $this->assertEquals($expected, $products);
    }

    public function searchResultsProvider()
    {
        $this->refreshApplication();

        $responseContent = File::get(base_path('tests/stubs/search-response.json'));
        $searchResults = json_decode($responseContent);

        return [
            [$searchResults],
        ];
    }
}
