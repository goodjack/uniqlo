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
        $this->call('hmall-product:fetch');
        $this->call('hmall-product-description:fetch');
        $this->call('hmall-product:cache');
        $this->call('sitemap:generate');
        $this->call('style:fetch');

        return 0;
    }
}
