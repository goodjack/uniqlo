<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class ProductFactory extends Factory
{
    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        $category1 = '00' . fake()->numberBetween(1, 4);
        $category2 = '00' . fake()->numberBetween(1, 9);

        return [
            'id' => fake()->randomNumber(6, true),
            'name' => fake()->sentence,
            'category_id' => $category = $category1 . $category2 . fake()->randomNumber(3, true),
            'categories' => "[\"{$category}\"]",
            'ancestors' => "[\"{$category1}\",\"{$category1}{$category2}\",\"{$category}\"]",
            'main_image_url' => fake()->imageUrl,
            'comment' => fake()->paragraph,
            'min_price' => $minPrice = fake()->numberBetween(39, 6990),
            'max_price' => $maxPrice = fake()->numberBetween($minPrice, 6990),
            'price' => fake()->numberBetween($minPrice, $maxPrice),
            'flags' => '[{"name":"ONLINE_ONLY_LIMITED_OFFER","value":"ONLINE_ONLY_LIMITED_OFFER"}]',
            'representative_sku_flags' => '[{"name":"ONLINE_ONLY_LIMITED_OFFER","value":"ONLINE_ONLY_LIMITED_OFFER"}]',
            'limit_sales_end_msg' => fake()->sentence,
            'multi_buy' => fake()->sentence,
            'new' => fake()->boolean,
            'sale' => fake()->boolean,
            'stockout' => fake()->boolean,
            'review_count' => fake()->randomNumber(1),
            'colors' => '[{"name":"{fake()->colorName}","code":"{fake()->randomNumber(2, true)}"},{"name":"{fake()->colorName}","code":"{fake()->randomNumber(2, true)}"}]',
            'sub_images' => '["182500_sub5"]',
            'style_dictionary_images' => null,
        ];
    }
}
