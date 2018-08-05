<?php

use Faker\Generator as Faker;

$factory->define(App\Product::class, function (Faker $faker) {
    return [
        'id' => $faker->randomNumber(6, true),
        'name' => $faker->sentence,
        'category_id' => $category = '001002'.$faker->randomNumber(3, true),
        'categories' => "[\"{$category}\"]",
        'ancestors' => "[\"001\",\"001002\",\"{$category}\"]",
        'main_image_url' => $faker->imageUrl,
        'comment' => $faker->paragraph,
        'price' => $faker->randomNumber(3, true),
        'min_price' => $faker->randomNumber(2, true),
        'max_price' => $faker->randomNumber(4, true),
        'flags' => '[{"name":"ONLINE_ONLY_LIMITED_OFFER","value":"ONLINE_ONLY_LIMITED_OFFER"}]',
        'representative_sku_flags' => '[{"name":"ONLINE_ONLY_LIMITED_OFFER","value":"ONLINE_ONLY_LIMITED_OFFER"}]',
        'limit_sales_end_msg' => $faker->sentence,
        'multi_buy' => $faker->sentence,
        'new' => $faker->boolean,
        'sale' => $faker->boolean,
        'stockout' => $faker->boolean,
        'colors' => "[{\"name\":\"{$faker->colorName}\",\"code\":\"{$faker->randomNumber(2, true)}\"},{\"name\":\"{$faker->colorName}\",\"code\":\"{$faker->randomNumber(2, true)}\"}]",
        'sub_images' => "[\"182500_sub5\"]",
        'style_dictionary_images' => null
    ];
});
