<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('facturas_electronicas', function (Blueprint $table) {
            $table->foreignId('evento_id')->nullable()->after('cafc')->constrained('eventos_contingencia')->nullOnDelete();
            $table->boolean('en_paquete')->default(false)->after('evento_id');
        });
    }

    public function down(): void
    {
        Schema::table('facturas_electronicas', function (Blueprint $table) {
            $table->dropConstrainedForeignId('evento_id');
            $table->dropColumn('en_paquete');
        });
    }
};
