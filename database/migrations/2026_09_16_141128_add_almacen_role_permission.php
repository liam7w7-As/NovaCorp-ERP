<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('roles') || ! Schema::hasTable('permiso_rol')) {
            return;
        }

        $ahora = now();

        DB::table('roles')->updateOrInsert(
            ['clave' => 'almacen'],
            [
                'nombre' => 'Almacén',
                'descripcion' => 'Preparación y entrega de pedidos',
                'es_sistema' => false,
                'updated_at' => $ahora,
                'created_at' => $ahora,
            ]
        );

        $roles = DB::table('roles')
            ->whereIn('clave', ['almacen', 'gerencia'])
            ->pluck('id', 'clave');

        foreach ($roles as $rolId) {
            DB::table('permiso_rol')->updateOrInsert(
                ['rol_id' => $rolId, 'habilidad' => 'almacen'],
                ['permitido' => true, 'updated_at' => $ahora, 'created_at' => $ahora]
            );
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('roles') || ! Schema::hasTable('permiso_rol')) {
            return;
        }

        $rolAlmacenId = DB::table('roles')->where('clave', 'almacen')->value('id');
        if ($rolAlmacenId) {
            DB::table('permiso_rol')->where('rol_id', $rolAlmacenId)->delete();
            DB::table('roles')->where('id', $rolAlmacenId)->delete();
        }

        $rolGerenciaId = DB::table('roles')->where('clave', 'gerencia')->value('id');
        if ($rolGerenciaId) {
            DB::table('permiso_rol')
                ->where('rol_id', $rolGerenciaId)
                ->where('habilidad', 'almacen')
                ->delete();
        }
    }
};
