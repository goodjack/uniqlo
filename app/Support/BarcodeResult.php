<?php

namespace App\Support;

use App\Enums\BarcodeStatus;
use App\Enums\Brand;

final class BarcodeResult
{
    /**
     * @param  string|null  $code  6 位數吊牌編號
     * @param  string|null  $url  found 是網站商品頁，not_listed 是官網商品頁
     */
    public function __construct(
        public readonly BarcodeStatus $status,
        public readonly string $barcode,
        public readonly ?string $code = null,
        public readonly ?string $url = null,
        public readonly ?Brand $brand = null,
    ) {}

    /**
     * @return array{status: string, url: string|null, barcode: string, code: string|null, brand: string|null}
     */
    public function toArray(): array
    {
        return [
            'status' => $this->status->value,
            'url' => $this->url,
            'barcode' => $this->barcode,
            'code' => $this->code,
            'brand' => $this->brand?->value,
        ];
    }
}
