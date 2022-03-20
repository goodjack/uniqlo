<?php

namespace App\Console\Commands;

use App\Services\StyleService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class FetchStyles extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'style:fetch';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fetch all styles from UNIQLO Styling Book';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct(StyleService $styleService)
    {
        parent::__construct();

        $this->styleService = $styleService;
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        Log::debug('FetchStyles start');

        $this->styleService->fetchAllStyles();

        Log::debug('FetchStyles end');
    }
}
