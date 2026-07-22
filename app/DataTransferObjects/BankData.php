<?php

namespace App\DataTransferObjects;

readonly class BankData
{
    public function __construct(
        public string $code,
        public string $name,
        public ?string $swiftCode = null,
        public bool $isActive = true,
        public ?string $notes = null,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            code: $data['code'],
            name: $data['name'],
            swiftCode: $data['swift_code'] ?? null,
            isActive: $data['is_active'] ?? true,
            notes: $data['notes'] ?? null,
        );
    }

    public function toArray(): array
    {
        return [
            'code' => $this->code,
            'name' => $this->name,
            'swift_code' => $this->swiftCode,
            'is_active' => $this->isActive,
            'notes' => $this->notes,
        ];
    }
}
