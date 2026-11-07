<?php

namespace App\Repositories;

use App\Models\Barcode;

class BarcodeRepository extends Repository
{
    protected $model;

    public function __construct(Barcode $model)
    {
        $this->model = $model;
    }

    public function findByBarcode(string $barcode): ?Barcode
    {
        return $this->model->where('barcode', $barcode)->first();
    }

    /**
     * 兩個人同時掃到同一個新條碼時，第二筆會撞唯一鍵，所以用 updateOrCreate。
     */
    public function save(array $attributes): Barcode
    {
        return $this->model->updateOrCreate(
            ['barcode' => $attributes['barcode']],
            $attributes,
        );
    }
}
