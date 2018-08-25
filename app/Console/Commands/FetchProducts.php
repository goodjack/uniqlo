<?php

namespace App\Console\Commands;

use App\Services\ProductService;
use Illuminate\Console\Command;

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
        $this->productService->fetchAllProducts();
        $this->productService->setStockoutProductTags();
        $this->call('price:set', [
            '--today' => true
        ]);
        $this->productService->fetchMultiBuyProducts();
        $this->call('multi-buy:set');
        $this->call('product:cache');
    }
}
