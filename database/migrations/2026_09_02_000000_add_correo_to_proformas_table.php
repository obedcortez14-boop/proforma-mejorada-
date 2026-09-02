<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Agrega la columna 'correo' (email del cliente) a la tabla 'proformas'.
     *
     * Es nullable y de tipo string para no romper las proformas existentes que
     * no cuentan con este dato. Se protege con Schema::hasColumn para que la
     * migración sea idempotente y segura si la columna ya existe (p. ej. Railway).
     */
    public function up(): void
    {
        if (! Schema::hasColumn('proformas', 'correo')) {
            Schema::table('proformas', function (Blueprint $table) {
                $table->string('correo')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('proformas', 'correo')) {
            Schema::table('proformas', function (Blueprint $table) {
                $table->dropColumn('correo');
            });
        }
    }
};
