<?php

namespace App\Console\Commands;

use App\Repositories\HmallProductRepository;
use Illuminate\Console\Command;

class CacheMostVisitedHmallProducts extends Command
{
    protected $signature = 'hmall-product:cache-most-visited';

    protected $description = 'Cache most visited hmall products';

    public function handle(HmallProductRepository $repository)
    {
        $this->info('Caching most visited hmall products...');

        $repository->setMostVisitedHmallProductsCache();

        $this->info('Most visited hmall products cached successfully!');

        return 0;
    }
}