<?php

namespace App\Console\Commands;

use App\Services\HmallProductService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class FetchGuHmallProducts extends Command
{
    private $hmallProductService;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'hmall-product:fetch-gu';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fetch all products from GU Hmall';

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
        Log::debug('FetchGuHmallProducts start');
        $this->hmallProductService->fetchAllHmallProducts('GU');
        Log::debug('FetchGuHmallProducts end');

        return 0;
    }
}
