<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class MultiBuyHistoryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        return [
            'product_id' => function () {
                return factory(App\Models\Product::class)->create()->id;
            },
            'multi_buy' => '購買{fake()->randomNumber(1)}件NT${fake()->randomNumber(2)}0.00',
        ];
    }
}
