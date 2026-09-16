<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('canal_whatsapps', function (Blueprint $table) {
            $table->foreignId('ultimo_vendedor_asignado_id')
                ->nullable()
                ->after('ultimo_error')
                ->constrained('users')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('canal_whatsapps', function (Blueprint $table) {
            $table->dropConstrainedForeignId('ultimo_vendedor_asignado_id');
        });
    }
};
