<?php

namespace App\Console\Commands;

use App\Services\ProductService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class FetchProducts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'product:fetch';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fetch all products from UNIQLO';

    /**
     * Create a new command instance.
     *
     * @param ProductService $productService
     *
     * @return void
     */
    public function __construct(ProductService $productService)
    {
        parent::__construct();

        $this->productService = $productService;
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        // TODO: queue

        Log::debug('fetchAllProducts start');
        $this->productService->fetchAllProducts();
        Log::debug('fetchAllProducts end');

        Log::debug('setStockoutProductTags start');
        $this->productService->setStockoutProductTags();
        Log::debug('setStockoutProductTags end');

        Log::debug('price:set start');
        $this->call('price:set', [
            '--today' => true,
        ]);
        Log::debug('price:set end');

        Log::debug('fetchMultiBuyProducts start');
        $this->productService->fetchMultiBuyProducts();
        Log::debug('fetchMultiBuyProducts end');

        Log::debug('multi-buy:set start');
        $this->call('multi-buy:set');
        Log::debug('multi-buy:set end');

        Log::debug('product:cache start');
        $this->call('product:cache');
        Log::debug('product:cache end');

        Log::debug('sitemap:generate start');
        $this->call('sitemap:generate');
        Log::debug('sitemap:generate end');
    }
}
