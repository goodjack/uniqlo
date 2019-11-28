<?php

namespace App\Console\Commands;

use App\Services\CategoryService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class FetchCategories extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'category:fetch';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fetch all categories from UNIQLO';

    protected $categoryService;

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct(CategoryService $categoryService)
    {
        parent::__construct();

        $this->categoryService = $categoryService;
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        Log::debug('FetchCategories start');

        $this->categoryService->fetchAllCategories();

        Log::debug('FetchCategories end');
    }
}
