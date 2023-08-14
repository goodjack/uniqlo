<?php

namespace App\Console\Commands;

use App\Services\HmallProductService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class FetchUniqloHmallProducts extends Command
{
    private $hmallProductService;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'hmall-product:fetch-uniqlo';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fetch all products from UNIQLO Hmall';

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
        Log::debug('FetchUniqloHmallProducts start');
        $this->hmallProductService->fetchAllHmallProducts('UNIQLO');
        Log::debug('FetchUniqloHmallProducts end');

        return 0;
    }
}
