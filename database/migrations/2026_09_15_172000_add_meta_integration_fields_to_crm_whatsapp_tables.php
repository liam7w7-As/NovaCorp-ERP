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
            $table->text('access_token')->nullable()->after('phone_number_id');
            $table->string('graph_version', 20)->nullable()->after('access_token');
            $table->timestamp('webhook_suscrito_at')->nullable()->after('estado');
            $table->text('ultimo_error')->nullable()->after('webhook_suscrito_at');
        });

        Schema::table('mensajes_whatsapp', function (Blueprint $table) {
            $table->string('meta_media_id')->nullable()->after('meta_message_id');
            $table->json('payload')->nullable()->after('archivo_url');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mensajes_whatsapp', function (Blueprint $table) {
            $table->dropColumn(['meta_media_id', 'payload']);
        });

        Schema::table('canal_whatsapps', function (Blueprint $table) {
            $table->dropColumn(['access_token', 'graph_version', 'webhook_suscrito_at', 'ultimo_error']);
        });
    }
};
