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
     * Execute the console command.
     *
     * @return int
     */
    public function handle(HmallProductRepository $hmallProductRepository)
    {
        $this->info('Setting Hmall product caches...');
        Log::debug('SetHmallProductCaches start');

        $hmallProductRepository->setLimitedOfferHmallProductsCache();
        $hmallProductRepository->setSaleHmallProductsCache();
        $hmallProductRepository->setMostReviewedHmallProductsCache();
        $hmallProductRepository->setJapanMostReviewedHmallProductsCache();
        $hmallProductRepository->setTopWearingHmallProductsCache();
        $hmallProductRepository->setNewHmallProductsCache();
        $hmallProductRepository->setComingSoonHmallProductsCache();
        $hmallProductRepository->setMultiBuyHmallProductsCache();
        $hmallProductRepository->setOnlineSpecialHmallProductsCache();

        Log::debug('SetHmallProductCaches end');
        $this->info('Set Hmall product caches');

        return 0;
    }
}
