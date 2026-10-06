<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * The payment provider refused or failed to process a refund.
 */
class PaymentRefundException extends RuntimeException {}
