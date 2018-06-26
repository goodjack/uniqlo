<?php

namespace App\Foundations;

trait DivideProducts
{
    /**
     * Divide products into men, women, kids and baby.
     *
     * @param array $products
     * @return array
     */
    public function divideProducts($products)
    {
        list($men, $others) = $products->partition(function ($product) {
            return starts_with($product->category_id, '001');
        });

        list($women, $others) = $others->partition(function ($product) {
            return starts_with($product->category_id, '002');
        });

        list($kids, $baby) = $others->partition(function ($product) {
            return starts_with($product->category_id, '003');
        });

        $result = [
            'men' => $men,
            'women' => $women,
            'kids' => $kids,
            'baby' => $baby,
        ];

        return $result;
    }
}
