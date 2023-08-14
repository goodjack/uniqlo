<?php

namespace App\Console\Commands;

use App\Services\HmallProductService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class FetchGuHmallProductDescriptions extends Command
{
    private $hmallProductService;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'hmall-product-description:fetch-gu';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fetch all Hmall Product description from GU SPU';

    /**
     * Create a new command instance.
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
        Log::debug('FetchGuHmallProductDescriptions start');
        $this->hmallProductService->fetchAllHmallProductDescriptions('GU');
        Log::debug('FetchGuHmallProductDescriptions end');

        return 0;
    }
}
