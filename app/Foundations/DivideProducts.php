<?php

namespace App\Foundations;

trait DivideProducts
{
    /**
     * Divide products into men, women, kids and baby.
     *
     * @param array $products
     *
     * @return array
     */
    public function divideProducts($products)
    {
        $groupMapper = [
            ['name' => 'men', 'code' => '001'],
            ['name' => 'women', 'code' => '002'],
            ['name' => 'kids', 'code' => '003'],
            ['name' => 'baby', 'code' => '004'],
        ];

        $groupedProducts = $products->groupBy(function ($product) {
            return substr($product->category_id, 0, 3);
        });

        $result = collect($groupMapper)->reduce(function ($carry, $item) use ($groupedProducts) {
            $carry[$item['name']] = $groupedProducts->get($item['code']);

            return $carry;
        }, []);

        return $result;
    }
}
