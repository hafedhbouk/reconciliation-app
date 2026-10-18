<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasUserstamps;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

/**
 * Modèle utilisateur de l'application de rapprochement.
 *
 * Champs profil métier :
 * - prenom : prénom de l'utilisateur
 * - nom : nom de famille de l'utilisateur
 * - matricule : identifiant unique métier
 * - portable : numéro de téléphone mobile
 *
 * La classe hérite de Authenticatable (et non de Model directement) : c'est
 * ce qui permet à Laravel de connecter cet utilisateur (login, session).
 * (Les notions fillable, casts et traits sont expliquées dans app/Models/Import.php.)
 */
class User extends Authenticatable
{
    /*
     * Traits propres aux utilisateurs :
     * - HasRoles   (paquet spatie/laravel-permission) : rôles et permissions,
     *              ex. $user->assignRole('admin'), $user->can('imports.create')
     * - Notifiable : permet d'envoyer des notifications ($user->notify(...))
     */
    /** @use HasFactory<UserFactory> */
    use Auditable, HasFactory, HasRoles, HasUserstamps, Notifiable, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'prenom',
        'nom',
        'matricule',
        'email',
        'portable',
        'password',
        'is_active', // un compte inactif ne peut pas se connecter
    ];

    /**
     * The attributes that should be hidden for serialization.
     * (Ces colonnes n'apparaissent jamais quand l'utilisateur est converti en
     * JSON ou en tableau, pour ne pas exposer le mot de passe.)
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     * 'hashed' : un mot de passe affecté est automatiquement chiffré (bcrypt)
     * avant d'être enregistré. On ne stocke jamais un mot de passe en clair.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'is_active' => 'boolean',
            'password' => 'hashed',
        ];
    }
}
