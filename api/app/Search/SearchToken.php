<?php

namespace App\Search;

final readonly class SearchToken
{
    public function __construct(
        public string $key,
        public string $value,
        public bool $negated = false,
    ) {}

    /**
     * @return array{key: string, value: string, negated: bool}
     */
    public function toArray(): array
    {
        return ['key' => $this->key, 'value' => $this->value, 'negated' => $this->negated];
    }
}
