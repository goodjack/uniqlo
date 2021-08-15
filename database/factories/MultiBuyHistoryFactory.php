<?php

namespace Database\Factories;

use App\Models\MultiBuyHistory;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

class MultiBuyHistoryFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = MultiBuyHistory::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        return [
            'product_id' => function () {
                return Product::factory()->create()->id;
            },
            'multi_buy' => "購買{$this->faker->randomNumber(1)}件NT\${$this->faker->randomNumber(2)}0.00",
        ];
    }
}
