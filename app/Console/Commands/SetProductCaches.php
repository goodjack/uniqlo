<?php

namespace App\Console\Commands;

use App\Repositories\ProductRepository;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SetProductCaches extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'product:cache';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Set product caches';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct(ProductRepository $productRepository)
    {
        parent::__construct();

        $this->productRepository = $productRepository;
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        Log::debug('SetProductCaches start');

        $this->productRepository->setLimitedOfferProductsCache();
        $this->productRepository->setMultiBuyProductsCache();
        $this->productRepository->setSaleProductsCache();
        $this->productRepository->setStockoutProductsCache();

        Log::debug('SetProductCaches end');
    }
}
