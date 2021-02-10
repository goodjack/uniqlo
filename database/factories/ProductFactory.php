<?php

use App\Models\Product;
use Faker\Generator as Faker;

$factory->define(Product::class, function (Faker $faker) {
    $category1 = '00'.$faker->numberBetween(1, 4);
    $category2 = '00'.$faker->numberBetween(1, 9);

    return [
        'id' => $faker->randomNumber(6, true),
        'name' => $faker->sentence,
        'category_id' => $category = $category1.$category2.$faker->randomNumber(3, true),
        'categories' => "[\"{$category}\"]",
        'ancestors' => "[\"{$category1}\",\"{$category1}{$category2}\",\"{$category}\"]",
        'main_image_url' => $faker->imageUrl,
        'comment' => $faker->paragraph,
        'min_price' => $minPrice = $faker->numberBetween(39, 6990),
        'max_price' => $maxPrice = $faker->numberBetween($minPrice, 6990),
        'price' => $faker->numberBetween($minPrice, $maxPrice),
        'flags' => '[{"name":"ONLINE_ONLY_LIMITED_OFFER","value":"ONLINE_ONLY_LIMITED_OFFER"}]',
        'representative_sku_flags' => '[{"name":"ONLINE_ONLY_LIMITED_OFFER","value":"ONLINE_ONLY_LIMITED_OFFER"}]',
        'limit_sales_end_msg' => $faker->sentence,
        'multi_buy' => $faker->sentence,
        'new' => $faker->boolean,
        'sale' => $faker->boolean,
        'stockout' => $faker->boolean,
        'review_count' => $faker->randomNumber(1),
        'colors' => "[{\"name\":\"{$faker->colorName}\",\"code\":\"{$faker->randomNumber(2, true)}\"},{\"name\":\"{$faker->colorName}\",\"code\":\"{$faker->randomNumber(2, true)}\"}]",
        'sub_images' => "[\"182500_sub5\"]",
        'style_dictionary_images' => null,
    ];
});
