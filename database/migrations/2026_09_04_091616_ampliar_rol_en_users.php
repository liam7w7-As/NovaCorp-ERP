<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Los roles ahora son dinámicos (tabla roles): rol pasa de ENUM a VARCHAR
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE users MODIFY COLUMN rol VARCHAR(50) NOT NULL DEFAULT 'vendedor'");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE users MODIFY COLUMN rol ENUM('admin','vendedor','contador') NOT NULL DEFAULT 'vendedor'");
        }
    }
};
