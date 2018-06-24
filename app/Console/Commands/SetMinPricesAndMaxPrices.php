<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\ProductService;

class SetMinPricesAndMaxPrices extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'price:set
                            {--today : Only set items updated today}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Set the min price and the max price to the products';

    /**
     * Create a new command instance.
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
        $today = $this->option('today');
        $this->productService->setMinPricesAndMaxPrices($today);
    }
}
