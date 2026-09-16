<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->string('rol', 50)->default('vendedor')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            return;
        }

        DB::table('users')
            ->whereNotIn('rol', ['admin', 'vendedor', 'contador'])
            ->update(['rol' => 'vendedor']);

        Schema::table('users', function (Blueprint $table) {
            $table->enum('rol', ['admin', 'vendedor', 'contador'])->default('vendedor')->change();
        });
    }
};
