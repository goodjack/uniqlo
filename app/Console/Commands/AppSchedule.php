<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class AppSchedule extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:schedule';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run the daily schedule';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->call('hmall-product:fetch', [
            'brand' => 'UNIQLO',
        ]);
        $this->call('hmall-product:fetch', [
            'brand' => 'GU',
        ]);
        $this->call('hmall-product-description:fetch', [
            'brand' => 'UNIQLO',
        ]);
        $this->call('hmall-product-description:fetch', [
            'brand' => 'GU',
        ]);
        $this->call('japan-product:fetch', [
            'brand' => 'UNIQLO',
        ]);
        $this->call('japan-product:fetch', [
            'brand' => 'GU',
        ]);
        $this->call('hmall-product:cache');
        $this->call('sitemap:generate');
        $this->call('style:fetch', [
            'brand' => 'UNIQLO',
        ]);
        $this->call('style:fetch', [
            'brand' => 'GU',
        ]);
        $this->call('style-hint:fetch', [
            'country' => 'us',
        ]);
        $this->call('style-hint:fetch', [
            'country' => 'jp',
        ]);
        $this->call('style-hint-ugc:fetch', [
            'country' => 'tw',
        ]);

        return 0;
    }
}
