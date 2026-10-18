<?php

namespace App\Contracts;

/**
 * Contrat d'une « transformation » élémentaire appliquée à une valeur lors de l'import.
 *
 * Chaque colonne d'un fichier importé peut passer par une chaîne de
 * transformations configurées en base (table source_column_mappings,
 * champ `transform`). Exemple pour le N° autorisation ALPHA « b697464 » :
 *   1. strip_prefix_chars  -> supprime le « b »        => « 697464 »
 *   2. zero_pad            -> complète à 6 chiffres    => « 697464 »
 *
 * Chaque étape est une petite classe (dossier app/Services/Import/Transforms)
 * qui implémente cette interface. TransformRegistry les retrouve par leur key().
 */
interface TransformPrimitive
{
    /**
     * Identifiant unique de la transformation, tel qu'il est écrit dans la
     * configuration en base (ex. 'trim', 'zero_pad', 'date_parse').
     */
    public static function key(): string;

    /**
     * Applique la transformation et renvoie la nouvelle valeur.
     *
     * @param  mixed  $value  la valeur actuelle (résultat de l'étape précédente)
     * @param  array<string,mixed>  $config  this step's config (e.g. ['chars' => ['B', 'b']])
     * @param  array<string,mixed>  $rawRow  the full raw row, for primitives needing cross-column context
     */
    public function apply(mixed $value, array $config, array $rawRow): mixed;
}
