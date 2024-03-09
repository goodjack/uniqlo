<?php

namespace App\Console\Commands;

use App\Services\SitemapService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class GenerateSitemap extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sitemap:generate';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate the sitemap.';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle(SitemapService $sitemapService)
    {
        $this->info('Generating sitemap...');
        Log::debug('GenerateSitemap start');

        $sitemapService->make();

        Log::debug('GenerateSitemap end');
        $this->info('Generated sitemap');
    }
}
