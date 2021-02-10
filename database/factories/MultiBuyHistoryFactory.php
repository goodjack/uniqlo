<?php

use App\Models\MultiBuyHistory;
use App\Models\Product;
use Faker\Generator as Faker;

$factory->define(MultiBuyHistory::class, function (Faker $faker) {
    return [
        'product_id' => function () {
            return Product::factory()->create()->id;
        },
        'multi_buy' => "購買{$faker->randomNumber(1)}件NT\${$faker->randomNumber(2)}0.00",
    ];
});
