<?php

namespace App\Services\Import;

use App\Contracts\TransformPrimitive;
use App\Enums\TransformType;
use App\Services\Import\Transforms\DateParseTransform;
use App\Services\Import\Transforms\DecimalStringToMillimesTransform;
use App\Services\Import\Transforms\FixedWidthMillimesTransform;
use App\Services\Import\Transforms\RightCharsTransform;
use App\Services\Import\Transforms\StripPrefixCharsTransform;
use App\Services\Import\Transforms\SubstringAfterNthDelimiterTransform;
use App\Services\Import\Transforms\TrimTransform;
use App\Services\Import\Transforms\ZeroPadTransform;
use InvalidArgumentException;

/**
 * Annuaire des transformations : associe un nom ('zero_pad') à sa classe (ZeroPadTransform).
 *
 * MappingEngine lit le nom écrit dans la configuration du mapping, puis
 * demande à ce registre l'objet correspondant.
 * Pour ajouter une nouvelle transformation :
 *   1. créer la classe dans app/Services/Import/Transforms (implémenter TransformPrimitive) ;
 *   2. ajouter un cas dans App\Enums\TransformType ;
 *   3. l'enregistrer dans le tableau ci-dessous.
 */
class TransformRegistry
{
    /** @var array<string,class-string<TransformPrimitive>> nom de la transformation => nom de la classe */
    private array $map;

    public function __construct()
    {
        // TransformType::Trim->value donne la chaîne 'trim'.
        $this->map = [
            TransformType::Trim->value => TrimTransform::class,
            TransformType::StripPrefixChars->value => StripPrefixCharsTransform::class,
            TransformType::FixedWidthMillimes->value => FixedWidthMillimesTransform::class,
            TransformType::DecimalStringToMillimes->value => DecimalStringToMillimesTransform::class,
            TransformType::DateParse->value => DateParseTransform::class,
            TransformType::SubstringAfterNthDelimiter->value => SubstringAfterNthDelimiterTransform::class,
            TransformType::ZeroPad->value => ZeroPadTransform::class,
            TransformType::RightChars->value => RightCharsTransform::class,
        ];
    }

    /** Renvoie l'objet transformation correspondant au nom, ou lève une erreur si le nom est inconnu. */
    public function make(string $key): TransformPrimitive
    {
        if (! isset($this->map[$key])) {
            throw new InvalidArgumentException("Transform primitive inconnu : {$key}");
        }

        return app($this->map[$key]);
    }
}
