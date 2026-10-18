<?php

namespace App\DataTransferObjects;

/**
 * DTO (Data Transfer Object) représentant les données d'une banque.
 *
 * Pour un débutant : un DTO est un simple « paquet » de données typées,
 * sans logique métier. Il sert à transporter des valeurs d'un endroit à
 * un autre (ex. du formulaire vers le modèle) avec des types garantis.
 *
 * `readonly` : une fois l'objet créé, ses propriétés ne peuvent plus être
 * modifiées. On évite ainsi les modifications accidentelles.
 */
readonly class BankData
{
    /**
     * « Promotion de propriétés » (PHP 8) : déclarer `public string $code`
     * dans le constructeur crée ET remplit automatiquement la propriété.
     */
    public function __construct(
        public string $code,
        public string $name,
        public ?string $swiftCode = null, // `?string` = chaîne OU null
        public bool $isActive = true,
        public ?string $notes = null,
    ) {}

    /**
     * Construit le DTO à partir d'un tableau (ex. données validées d'un formulaire).
     * Les clés du tableau sont en snake_case (comme en base), les propriétés en camelCase.
     * L'opérateur `??` donne une valeur par défaut si la clé est absente.
     */
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

    /** Opération inverse : renvoie un tableau prêt pour Bank::create() / update(). */
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
