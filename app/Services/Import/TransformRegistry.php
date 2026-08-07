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

class TransformRegistry
{
    /** @var array<string,class-string<TransformPrimitive>> */
    private array $map;

    public function __construct()
    {
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

    public function make(string $key): TransformPrimitive
    {
        if (! isset($this->map[$key])) {
            throw new InvalidArgumentException("Transform primitive inconnu : {$key}");
        }

        return app($this->map[$key]);
    }
}
