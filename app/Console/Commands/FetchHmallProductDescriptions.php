<?php

namespace App\Console\Commands;

use App\Services\HmallProductService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class FetchHmallProductDescriptions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'hmall-product-description:fetch';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fetch all Hmall Product description from UNIQLO SPU';

    /**
     * Create a new command instance.
     *
     * @param HmallProductService $hmallProductService
     *
     * @return void
     */
    public function __construct(HmallProductService $hmallProductService)
    {
        parent::__construct();

        $this->hmallProductService = $hmallProductService;
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        Log::debug('FetchHmallProductDescriptions start');
        $this->hmallProductService->fetchAllHmallProductDescriptions();
        Log::debug('FetchHmallProductDescriptions end');

        $this->call('sitemap:generate');
    }
}
