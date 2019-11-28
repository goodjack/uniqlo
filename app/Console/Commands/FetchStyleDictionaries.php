<?php

namespace App\Console\Commands;

use App\Services\ProductService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class FetchStyleDictionaries extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'style-dict:fetch';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fetch all style dictionaries from UNIQLO';

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
        Log::debug('FetchStyleDictionaries start');

        $this->productService->fetchAllStyleDictionaries();

        Log::debug('FetchStyleDictionaries end');
    }
}
