<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // El SIN envía descripciones de productos de 300+ caracteres.
        // SQLite no limita varchar; solo MySQL necesita el ALTER.
        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE `catalogos_sin` MODIFY `descripcion` TEXT NOT NULL');
        }
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE `catalogos_sin` MODIFY `descripcion` VARCHAR(255) NOT NULL');
        }
    }
};
