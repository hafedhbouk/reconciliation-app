<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Classe parente des Form Requests de l'application.
 *
 * ---------------------------------------------------------------------------
 * GUIDE DÉBUTANT : qu'est-ce qu'un « Form Request » ? (valable pour app/Http/Requests)
 * ---------------------------------------------------------------------------
 * Une classe dédiée à la VALIDATION d'un formulaire. Quand une méthode de
 * contrôleur demande un paramètre StoreBankRequest $request, Laravel valide
 * les données AVANT d'appeler la méthode :
 *   - si une règle échoue : retour automatique au formulaire, avec les
 *     messages d'erreur sous les champs et les valeurs déjà saisies ;
 *   - si tout est bon : la méthode s'exécute et $request->validated()
 *     renvoie uniquement les champs validés.
 *
 * Règles les plus courantes dans rules() :
 *   'required'               champ obligatoire
 *   'nullable'               le champ peut être vide
 *   'string' / 'integer'     type attendu
 *   'max:255' / 'size:3'     longueur maximale / exacte
 *   'email' / 'date'         format attendu
 *   'boolean'                vrai/faux (case à cocher)
 *   'exists:sources,id'      la valeur doit exister dans la colonne id de la table sources
 *   'unique:banks,code'      la valeur ne doit PAS déjà exister dans banks.code
 *   'different:champ'        doit être différent d'un autre champ
 *   'mimes:csv,xlsx'         types de fichier acceptés
 *   'champ.*'                règle appliquée à chaque élément d'un tableau
 *   Rule::unique(...)->ignore($id)   unique, sauf pour la ligne qu'on modifie
 *                            (sinon modifier une banque sans changer son code échouerait).
 * withValidator() permet d'ajouter des contrôles plus complexes, exécutés après les règles.
 *
 * authorize() renvoie toujours true ici : les droits ne sont pas vérifiés dans
 * les Form Requests mais dans les Policies (app/Policies), via les contrôleurs.
 */
abstract class BaseFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }
}
