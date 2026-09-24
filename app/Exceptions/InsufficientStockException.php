<?php

namespace App\Exceptions;

use RuntimeException;

class InsufficientStockException extends RuntimeException
{
    public function __construct(public readonly string $productName)
    {
        parent::__construct("Not enough stock for {$productName}.");
    }
}
