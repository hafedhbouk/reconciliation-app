<?php

namespace App\Support;

use Carbon\CarbonInterface;
use DateTimeInterface;
use Illuminate\Support\Carbon;

/**
 * Affichage des dates en français, au même format partout.
 *
 * Deux sortes de dates, à ne pas confondre :
 *   - un INSTANT (created_at, matched_at, started_at…) : enregistré en UTC
 *     dans la base, converti dans le fuseau d'affichage (config
 *     app.display_timezone, Africa/Tunis) uniquement ici, au moment de l'afficher.
 *     Ex. 2026-10-22T19:50:08Z → « 22/10/2026 à 20:50:08 ».
 *   - une DATE CALENDAIRE (date d'opération d'une transaction, sans heure) :
 *     jamais décalée. Ex. 2026-07-01 → « 01/07/2026 ».
 *
 * Dans les vues Blade : @datetime($import->created_at) (voir AppServiceProvider).
 * Une valeur vide renvoie null : la vue choisit quoi afficher (souvent « — »).
 */
final class DateDisplay
{
    /** Format d'un instant : 22/10/2026 à 19:50:08. */
    public const DATE_TIME = 'd/m/Y à H:i:s';

    /** Format d'une date : 22/10/2026. */
    public const DATE = 'd/m/Y';

    /** Instant converti dans le fuseau d'affichage : « 22/10/2026 à 19:50:08 ». */
    public static function dateTime(mixed $value): ?string
    {
        return self::instant($value)?->format(self::DATE_TIME);
    }

    /** Jour d'un instant, dans le fuseau d'affichage : « 22/10/2026 ». */
    public static function dateOf(mixed $value): ?string
    {
        return self::instant($value)?->format(self::DATE);
    }

    /** Date calendaire, sans conversion de fuseau : « 01/07/2026 ». */
    public static function date(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        $date = $value instanceof DateTimeInterface ? $value : Carbon::parse((string) $value);

        return $date->format(self::DATE);
    }

    /** Durée écoulée, en français : « il y a 5 minutes ». */
    public static function relative(mixed $value): ?string
    {
        return self::instant($value)?->locale(app()->getLocale())->diffForHumans();
    }

    /** Fuseau d'affichage configuré. */
    public static function timezone(): string
    {
        return (string) config('app.display_timezone', config('app.timezone'));
    }

    /** Instant dans le fuseau d'affichage ; une chaîne sans fuseau est lue comme de l'UTC (stockage). */
    private static function instant(mixed $value): ?CarbonInterface
    {
        if ($value === null || $value === '') {
            return null;
        }
        $instant = $value instanceof DateTimeInterface
            ? Carbon::instance($value)
            : Carbon::parse((string) $value, config('app.timezone'));

        return $instant->copy()->setTimezone(self::timezone());
    }
}
