<?php

namespace App\Http\Controllers;

use App\Services\BarcodeService;
use App\Support\BarcodeResult;
use App\Support\ClientKey;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BarcodeController extends Controller
{
    protected $service;

    public function __construct(BarcodeService $service)
    {
        $this->service = $service;
    }

    public function show(Request $request, string $barcode): JsonResponse
    {
        abort_unless(config('barcode.enabled'), 404);

        return self::respond($this->service->lookup($barcode, ClientKey::fromIp($request->ip())));
    }

    public static function respond(BarcodeResult $result, int $status = 200, array $headers = []): JsonResponse
    {
        return response()
            ->json($result->toArray(), $status, $headers)
            ->header('X-Robots-Tag', 'noindex');
    }
}
