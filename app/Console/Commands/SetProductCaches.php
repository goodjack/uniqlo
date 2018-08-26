<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Repositories\ProductRepository;

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
        $this->productRepository->setLimitedOfferProductsCache();
        $this->productRepository->setMultiBuyProductsCache();
        $this->productRepository->setSaleProductsCache();
        $this->productRepository->setStockoutProductsCache();
    }
}
