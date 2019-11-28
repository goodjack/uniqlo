<?php

namespace App\Console\Commands;

use App\Repositories\ProductRepository;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SetMultiBuyProducts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'multi-buy:set';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Set the promos to each MULTY_BUY product';

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
        Log::debug('SetMultiBuyProducts start');

        $this->productRepository->setMultiBuyProducts();

        Log::debug('SetMultiBuyProducts end');
    }
}
