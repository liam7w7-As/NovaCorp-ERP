<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('canal_whatsapps', function (Blueprint $table) {
            $table->string('plantilla_nombre', 100)->nullable()->after('graph_version');
            $table->string('plantilla_idioma', 10)->default('es')->after('plantilla_nombre');
        });

        // Nuevo tipo para mensajes enviados por plantilla aprobada de Meta.
        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE `mensajes_whatsapp` MODIFY `tipo` ENUM('texto','imagen','documento','audio','video','interactivo','plantilla') NOT NULL DEFAULT 'texto'");

            return;
        }

        // SQLite no permite ALTER de CHECK: reconstruir la tabla.
        Schema::create('mensajes_whatsapp_new', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->constrained()->cascadeOnDelete();
            $table->foreignId('canal_whatsapp_id')->nullable()->constrained('canal_whatsapps')->nullOnDelete();
            $table->foreignId('enviado_por_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('meta_message_id')->nullable()->unique();
            $table->string('meta_media_id')->nullable();
            $table->enum('direccion', ['entrante', 'saliente']);
            $table->enum('tipo', ['texto', 'imagen', 'documento', 'audio', 'video', 'interactivo', 'plantilla'])->default('texto');
            $table->text('contenido')->nullable();
            $table->string('archivo_url')->nullable();
            $table->string('mime_type')->nullable();
            $table->string('nombre_archivo')->nullable();
            $table->unsignedBigInteger('tamano_archivo')->nullable();
            $table->json('payload')->nullable();
            $table->enum('estado', ['recibido', 'pendiente', 'enviado', 'entregado', 'leido', 'error', 'simulado']);
            $table->timestamp('ocurrio_at');
            $table->timestamps();

            $table->index(['lead_id', 'ocurrio_at']);
            $table->index(['estado', 'ocurrio_at']);
        });

        DB::statement('INSERT INTO mensajes_whatsapp_new (id, lead_id, canal_whatsapp_id, enviado_por_id, meta_message_id, meta_media_id, direccion, tipo, contenido, archivo_url, mime_type, nombre_archivo, tamano_archivo, payload, estado, ocurrio_at, created_at, updated_at) SELECT id, lead_id, canal_whatsapp_id, enviado_por_id, meta_message_id, meta_media_id, direccion, tipo, contenido, archivo_url, mime_type, nombre_archivo, tamano_archivo, payload, estado, ocurrio_at, created_at, updated_at FROM mensajes_whatsapp');
        Schema::drop('mensajes_whatsapp');
        Schema::rename('mensajes_whatsapp_new', 'mensajes_whatsapp');
    }

    public function down(): void
    {
        Schema::table('canal_whatsapps', function (Blueprint $table) {
            $table->dropColumn(['plantilla_nombre', 'plantilla_idioma']);
        });

        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement("UPDATE `mensajes_whatsapp` SET `tipo` = 'texto' WHERE `tipo` = 'plantilla'");
            DB::statement("ALTER TABLE `mensajes_whatsapp` MODIFY `tipo` ENUM('texto','imagen','documento','audio','video','interactivo') NOT NULL DEFAULT 'texto'");
        }
    }
};
