<?php

namespace App\DataTransferObjects;

readonly class SourceData
{
    public function __construct(
        public string $code,
        public string $name,
        public string $fileType,
        public ?int $bankId = null,
        public ?int $defaultCurrencyId = null,
        public bool $isActive = true,
        public ?string $description = null,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            code: $data['code'],
            name: $data['name'],
            fileType: $data['file_type'],
            bankId: $data['bank_id'] ?? null,
            defaultCurrencyId: $data['default_currency_id'] ?? null,
            isActive: $data['is_active'] ?? true,
            description: $data['description'] ?? null,
        );
    }

    public function toArray(): array
    {
        return [
            'code' => $this->code,
            'name' => $this->name,
            'file_type' => $this->fileType,
            'bank_id' => $this->bankId,
            'default_currency_id' => $this->defaultCurrencyId,
            'is_active' => $this->isActive,
            'description' => $this->description,
        ];
    }
}
