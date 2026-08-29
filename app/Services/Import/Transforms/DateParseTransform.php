<?php

namespace App\Services\Import\Transforms;

/**
 * Transforme DateParse : analyse une date selon un format donné et
 * retourne une date (Y-m-d) ou un datetime (Y-m-d H:i:s).
 *
 * Le format attendu est fourni via config['format'] (ex: 'd/m/Y').
 * Un format invalide ou une valeur non conforme lève une
 * TransformException et marque la ligne en erreur.
 */
use App\Contracts\TransformPrimitive;
use App\Enums\TransformType;
use App\Exceptions\Import\TransformException;
use Carbon\Carbon;

class DateParseTransform implements TransformPrimitive
{
    public static function key(): string
    {
        return TransformType::DateParse->value;
    }

    public function apply(mixed $value, array $config, array $rawRow): mixed
    {
        $format = $config['format'] ?? throw new TransformException('Le paramètre "format" est requis pour date_parse.');
        $output = $config['output'] ?? 'date';
        $trimmed = trim((string) $value);

        try {
            $parsed = Carbon::createFromFormat($format, $trimmed);
        } catch (\Throwable $e) {
            throw new TransformException("Impossible d'analyser '{$value}' au format '{$format}'.", previous: $e);
        }

        if ($parsed === false) {
            throw new TransformException("Impossible d'analyser '{$value}' au format '{$format}'.");
        }

        return $output === 'datetime' ? $parsed->format('Y-m-d H:i:s') : $parsed->format('Y-m-d');
    }
}
