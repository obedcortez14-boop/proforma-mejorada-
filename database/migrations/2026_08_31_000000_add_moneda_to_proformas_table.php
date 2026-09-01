<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Agrega la columna 'moneda' a la tabla 'proformas' para persistir la
     * moneda elegida por el usuario: '$' = Dólares (USD), 'C$' = Córdobas (NIO).
     *
     * Se protege con Schema::hasColumn para que la migración sea idempotente
     * y segura si la columna ya existe en la base de datos (p. ej. Railway).
     */
    public function up(): void
    {
        if (! Schema::hasColumn('proformas', 'moneda')) {
            Schema::table('proformas', function (Blueprint $table) {
                $table->string('moneda', 10)->nullable()->default('$');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('proformas', 'moneda')) {
            Schema::table('proformas', function (Blueprint $table) {
                $table->dropColumn('moneda');
            });
        }
    }
};
