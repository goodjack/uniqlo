<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class AppScheduleForStyleHint extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:style-hint-schedule';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run the daily schedule for StyleHint';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->call('style-hint:fetch', [
            'country' => 'us',
        ]);
        $this->call('style-hint:fetch', [
            'country' => 'jp',
        ]);

        return 0;
    }
}
