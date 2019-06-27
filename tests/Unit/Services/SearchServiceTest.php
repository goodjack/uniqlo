<?php

namespace Tests\Unit\Services;

use App\Product;
use Tests\TestCase;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;
use App\Services\SearchService;
use App\Foundations\DivideProducts;
use Illuminate\Support\Facades\File;
use Illuminate\Foundation\Testing\RefreshDatabase;

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

    public function test_getProducts()
    {
        $fakeSearchResults = $this->getFakeSearchResults();

        factory(Product::class)->create([
            'id' => 409325,
        ]);

        factory(Product::class)->create([
            'id' => 40932500001,
        ]);

        factory(Product::class, 10)->create();

        $service = app(SearchService::class);
        $products = $service->getProducts($fakeSearchResults);

        $expected = $this->divideProducts(
            Product::where('id', 409325)
            ->orWhere('id', 40932500001)
            ->get()
        );

        $this->assertEquals($expected, $products);
    }

    private function getFakeSearchResults()
    {
        $responseContent = File::get(base_path('tests/stubs/search-response.json'));

        return json_decode($responseContent);
    }
}
