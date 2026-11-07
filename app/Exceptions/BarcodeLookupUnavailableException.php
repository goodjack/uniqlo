<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * 官網這次沒給出可以信的答案（連不上、被擋、格式不對、用完每日上限）。
 * 跟「查不到」不同：不能存、也不能當成查不到放進快取。
 */
class BarcodeLookupUnavailableException extends RuntimeException {}
