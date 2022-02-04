<?php

namespace App\Console\Commands;

use App\Repositories\HmallProductRepository;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SetHmallProductCaches extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'hmall-product:cache';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Set Hmall product caches';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct(HmallProductRepository $hmallProductRepository)
    {
        parent::__construct();

        $this->hmallProductRepository = $hmallProductRepository;
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        Log::debug('SetHmallProductCaches start');
        $this->hmallProductRepository->setLimitedOfferHmallProductsCache();
        $this->hmallProductRepository->setSaleHmallProductsCache();
        Log::debug('SetHmallProductCaches end');

        return 0;
    }
}
