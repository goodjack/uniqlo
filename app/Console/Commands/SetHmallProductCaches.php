<?php

namespace App\Console\Commands;

use App\Events\AppTaskFinished;
use App\Events\AppTaskStarting;
use App\Repositories\HmallProductRepository;
use Illuminate\Console\Command;

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
        AppTaskStarting::dispatch(class_basename(__CLASS__));

        $hmallProductRepository->setLimitedOfferHmallProductsCache();
        $hmallProductRepository->setSaleHmallProductsCache();
        $hmallProductRepository->setMostReviewedHmallProductsCache();
        $hmallProductRepository->setJapanMostReviewedHmallProductsCache();
        $hmallProductRepository->setTopWearingHmallProductsCache();
        $hmallProductRepository->setNewHmallProductsCache();
        $hmallProductRepository->setComingSoonHmallProductsCache();
        $hmallProductRepository->setMultiBuyHmallProductsCache();
        $hmallProductRepository->setOnlineSpecialHmallProductsCache();

        AppTaskFinished::dispatch(class_basename(__CLASS__));
        $this->info('Set Hmall product caches');

        return 0;
    }
}
