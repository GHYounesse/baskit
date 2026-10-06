<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * The order isn't in a state where that action makes sense (e.g. shipping
 * an order that was already refunded). The message is safe to show.
 */
class InvalidOrderTransitionException extends RuntimeException {}
