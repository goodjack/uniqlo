<?php

use Faker\Generator as Faker;

$factory->define(App\MultiBuyHistory::class, function (Faker $faker) {
    return [
        'product_id' => function () {
            return factory(App\Product::class)->create()->id;
        },
        'multi_buy' => "購買{$faker->randomNumber(1)}件NT\${$faker->randomNumber(2)}0.00",
    ];
});
