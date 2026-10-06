<?php

namespace App\Services\Assistant;

/**
 * What the assistant took from a message: which kind of request it is, and
 * the details found in it (a price limit, a size, an order number...).
 */
final class Understanding
{
    /**
     * @param  array<string, mixed>  $entities
     */
    public function __construct(
        public readonly string $intent,
        public readonly array $entities = [],
    ) {}

    public function entity(string $name, mixed $default = null): mixed
    {
        return $this->entities[$name] ?? $default;
    }

    public function with(array $entities): self
    {
        return new self($this->intent, [...$this->entities, ...$entities]);
    }
}
