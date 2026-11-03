<?php

namespace App\Import;

final readonly class ImportRow
{
    /**
     * @param  list<string>  $tags
     * @param  list<string>  $errors
     */
    public function __construct(
        public int $line,
        public ?string $date,
        public ?string $amount,
        public string $description,
        public ?string $counterparty = null,
        public ?string $category = null,
        public ?string $notes = null,
        public array $tags = [],
        public ?string $externalId = null,
        public array $errors = [],
    ) {}

    public function isValid(): bool
    {
        return $this->errors === [];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'line' => $this->line,
            'date' => $this->date,
            'amount' => $this->amount,
            'description' => $this->description,
            'counterparty' => $this->counterparty,
            'category' => $this->category,
            'notes' => $this->notes,
            'tags' => $this->tags,
            'external_id' => $this->externalId,
            'errors' => $this->errors,
        ];
    }
}
