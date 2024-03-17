<?php

namespace App\Console\Commands;

use App\Events\AppTaskFinished;
use App\Events\AppTaskStarting;
use App\Services\SitemapService;
use Illuminate\Console\Command;

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
        AppTaskStarting::dispatch(class_basename(__CLASS__));

        $sitemapService->make();

        AppTaskFinished::dispatch(class_basename(__CLASS__));
        $this->info('Generated sitemap');
    }
}
