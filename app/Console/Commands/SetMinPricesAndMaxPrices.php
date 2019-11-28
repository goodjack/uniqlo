<?php

namespace App\Console\Commands;

use App\Services\ProductService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

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
        Log::debug('SetMinPricesAndMaxPrices start');

        $today = $this->option('today');
        $this->productService->setMinPricesAndMaxPrices($today);

        Log::debug('SetMinPricesAndMaxPrices end');
    }
}
