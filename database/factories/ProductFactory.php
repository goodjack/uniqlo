<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class ProductFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        $category1 = '00' . $this->faker->numberBetween(1, 4);
        $category2 = '00' . $this->faker->numberBetween(1, 9);

        return [
            'id' => $this->faker->randomNumber(6, true),
            'name' => $this->faker->sentence,
            'category_id' => $category = $category1 . $category2 . $this->faker->randomNumber(3, true),
            'categories' => "[\"{$category}\"]",
            'ancestors' => "[\"{$category1}\",\"{$category1}{$category2}\",\"{$category}\"]",
            'main_image_url' => $this->faker->imageUrl,
            'comment' => $this->faker->paragraph,
            'min_price' => $minPrice = $this->faker->numberBetween(39, 6990),
            'max_price' => $maxPrice = $this->faker->numberBetween($minPrice, 6990),
            'price' => $this->faker->numberBetween($minPrice, $maxPrice),
            'flags' => '[{"name":"ONLINE_ONLY_LIMITED_OFFER","value":"ONLINE_ONLY_LIMITED_OFFER"}]',
            'representative_sku_flags' => '[{"name":"ONLINE_ONLY_LIMITED_OFFER","value":"ONLINE_ONLY_LIMITED_OFFER"}]',
            'limit_sales_end_msg' => $this->faker->sentence,
            'multi_buy' => $this->faker->sentence,
            'new' => $this->faker->boolean,
            'sale' => $this->faker->boolean,
            'stockout' => $this->faker->boolean,
            'review_count' => $this->faker->randomNumber(1),
            'colors' => "[{\"name\":\"{$this->faker->colorName}\",\"code\":\"{$this->faker->randomNumber(2, true)}\"},{\"name\":\"{$this->faker->colorName}\",\"code\":\"{$this->faker->randomNumber(2, true)}\"}]",
            'sub_images' => '["182500_sub5"]',
            'style_dictionary_images' => null,
        ];
    }
}
