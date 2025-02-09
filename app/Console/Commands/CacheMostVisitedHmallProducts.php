<?php

namespace App\Console\Commands;

use App\Events\AppTaskFinished;
use App\Events\AppTaskStarting;
use App\Repositories\HmallProductRepository;
use Illuminate\Console\Command;

class CacheMostVisitedHmallProducts extends Command
{
    protected $signature = 'hmall-product:cache-most-visited';

    protected $description = 'Cache most visited hmall products';

    public function handle(HmallProductRepository $repository)
    {
        $this->info('Caching most visited hmall products...');
        AppTaskStarting::dispatch(class_basename(__CLASS__));

        $repository->setMostVisitedHmallProductsCache();

        $this->info('Most visited hmall products cached successfully!');
        AppTaskFinished::dispatch(class_basename(__CLASS__));

        return 0;
    }
}
