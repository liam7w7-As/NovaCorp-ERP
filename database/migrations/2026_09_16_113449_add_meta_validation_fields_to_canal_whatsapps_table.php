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
            $table->string('meta_display_phone_number')->nullable()->after('phone_number_id');
            $table->string('meta_verified_name')->nullable()->after('meta_display_phone_number');
            $table->string('meta_quality_rating', 50)->nullable()->after('meta_verified_name');
            $table->string('meta_code_verification_status', 80)->nullable()->after('meta_quality_rating');
            $table->timestamp('meta_verificado_at')->nullable()->after('webhook_suscrito_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('canal_whatsapps', function (Blueprint $table) {
            $table->dropColumn([
                'meta_display_phone_number',
                'meta_verified_name',
                'meta_quality_rating',
                'meta_code_verification_status',
                'meta_verificado_at',
            ]);
        });
    }
};
