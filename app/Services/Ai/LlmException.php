<?php

namespace App\Services\Ai;

use RuntimeException;

/**
 * The language model could not give a usable answer (not configured,
 * timed out, rate limited, or answered with something unreadable).
 */
class LlmException extends RuntimeException {}
