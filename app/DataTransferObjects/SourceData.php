<?php

namespace App\DataTransferObjects;

/**
 * DTO des données d'une « source » (un type de fichier importé : ALPHA, BNA, WEB, SMT…).
 * Même principe que BankData : voir ce fichier pour les explications détaillées.
 */
readonly class SourceData
{
    public function __construct(
        public string $code,                  // code court unique, ex. 'BNA'
        public string $name,                  // libellé affiché
        public string $fileType,              // 'csv', 'xls' ou 'xlsx' (voir App\Enums\FileType)
        public ?int $bankId = null,           // banque rattachée (facultatif)
        public ?int $defaultCurrencyId = null, // devise utilisée si le fichier n'en précise pas
        public bool $isActive = true,
        public ?string $description = null,
    ) {}

    /** Construit le DTO à partir d'un tableau en snake_case (ex. données validées). */
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

    /** Renvoie un tableau prêt pour Source::create() / update(). */
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
