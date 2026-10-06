<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * A coupon can't be used. The message is written for the customer.
 */
class CouponException extends RuntimeException {}
