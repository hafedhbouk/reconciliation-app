<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * Retrait du module « Jours fériés » (Phase 5) : suppression des permissions
 * holidays.* devenues inutiles, qui apparaissaient encore dans
 * l'administration des rôles.
 *
 * Rien n'est perdu :
 *   - la table holidays et ses données éventuelles sont conservées ;
 *   - avant la suppression, les permissions et leurs attributions (rôles et
 *     utilisateurs) sont enregistrées dans storage/app/holidays-permissions-*.json ;
 *   - down() les recrée à l'identique à partir de ce fichier.
 * Les autres permissions et rôles ne sont pas touchés.
 */
return new class extends Migration
{
    public function up(): void
    {
        $permissions = DB::table('permissions')->where('name', 'like', 'holidays.%')->get(['id', 'name', 'guard_name']);
        if ($permissions->isEmpty()) {
            return;
        }
        $ids = $permissions->pluck('id');
        $names = $permissions->pluck('name', 'id');

        $snapshot = [
            'removed_at' => now()->toIso8601String(),
            'permissions' => $permissions->map(fn ($permission) => ['name' => $permission->name, 'guard_name' => $permission->guard_name])->values(),
            'roles' => DB::table('role_has_permissions')->join('roles', 'roles.id', '=', 'role_has_permissions.role_id')
                ->whereIn('permission_id', $ids)->get(['roles.name as role', 'roles.guard_name', 'permission_id'])
                ->map(fn ($row) => ['role' => $row->role, 'guard_name' => $row->guard_name, 'permission' => $names[$row->permission_id]])->values(),
            'models' => DB::table('model_has_permissions')->whereIn('permission_id', $ids)->get(['model_type', 'model_id', 'permission_id'])
                ->map(fn ($row) => ['model_type' => $row->model_type, 'model_id' => $row->model_id, 'permission' => $names[$row->permission_id]])->values(),
        ];
        $file = storage_path('app/holidays-permissions-'.now()->format('Ymd-His').'.json');
        if (file_put_contents($file, json_encode($snapshot, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) === false) {
            throw new RuntimeException("Sauvegarde des permissions impossible ({$file}) : rien n'a été supprimé.");
        }

        DB::transaction(function () use ($ids) {
            DB::table('role_has_permissions')->whereIn('permission_id', $ids)->delete();
            DB::table('model_has_permissions')->whereIn('permission_id', $ids)->delete();
            DB::table('permissions')->whereIn('id', $ids)->delete();
        });
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        $files = glob(storage_path('app/holidays-permissions-*.json')) ?: [];
        sort($files);
        $snapshot = $files === [] ? null : json_decode((string) file_get_contents(end($files)), true);
        // Sans sauvegarde : permissions recréées sans attribution (à redonner dans Administration > Rôles).
        $permissions = $snapshot['permissions'] ?? array_map(fn ($ability) => ['name' => "holidays.{$ability}", 'guard_name' => 'web'],
            ['viewAny', 'view', 'create', 'update', 'delete', 'restore']);

        foreach ($permissions as $permission) {
            DB::table('permissions')->updateOrInsert($permission, ['created_at' => now(), 'updated_at' => now()]);
        }
        $ids = DB::table('permissions')->where('name', 'like', 'holidays.%')->pluck('id', 'name');
        foreach ($snapshot['roles'] ?? [] as $row) {
            $roleId = DB::table('roles')->where('name', $row['role'])->where('guard_name', $row['guard_name'])->value('id');
            if ($roleId !== null && isset($ids[$row['permission']])) {
                DB::table('role_has_permissions')->insertOrIgnore(['role_id' => $roleId, 'permission_id' => $ids[$row['permission']]]);
            }
        }
        foreach ($snapshot['models'] ?? [] as $row) {
            if (isset($ids[$row['permission']])) {
                DB::table('model_has_permissions')->insertOrIgnore(['model_type' => $row['model_type'], 'model_id' => $row['model_id'], 'permission_id' => $ids[$row['permission']]]);
            }
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
