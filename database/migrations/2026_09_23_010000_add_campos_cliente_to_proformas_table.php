<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Agrega a 'proformas' las columnas de cliente/detalle que el controlador ya escribe.
 *
 * Contexto: CalculadoraController::generarPDF() y update() guardan 'vendedor',
 * 'ruc_cedula', 'telefono', 'direccion_proyecto', 'condiciones' y 'descuento', pero
 * NINGUNA migración las creaba. En PostgreSQL (Railway) la tabla se alteró por fuera
 * de las migraciones (deriva de esquema), mientras que en SQLite (entorno local y
 * pruebas) su ausencia provocaba el error:
 *   "table proformas has no column named vendedor".
 *
 * La migración es 100% IDEMPOTENTE gracias a las guardas Schema::hasColumn(): en
 * Railway, donde las columnas ya existen con datos reales, no hace absolutamente nada.
 *
 * ⚠️ down() no elimina las columnas a propósito: contienen información real de
 * clientes (RUC, teléfono, dirección, condiciones) y borrarlas en un rollback
 * destruiría datos de producción. Para limpiar el entorno local basta con
 * 'php artisan migrate:fresh'.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('proformas', function (Blueprint $table) {
            // Cada bloque agrega la columna SÓLO si falta (idempotencia total).
            if (!Schema::hasColumn('proformas', 'vendedor')) {
                $table->string('vendedor')->nullable();
            }

            if (!Schema::hasColumn('proformas', 'ruc_cedula')) {
                $table->string('ruc_cedula')->nullable();
            }

            if (!Schema::hasColumn('proformas', 'telefono')) {
                $table->string('telefono')->nullable();
            }

            if (!Schema::hasColumn('proformas', 'direccion_proyecto')) {
                $table->string('direccion_proyecto')->nullable();
            }

            if (!Schema::hasColumn('proformas', 'condiciones')) {
                // Texto libre con varias líneas (viñetas de condiciones comerciales).
                $table->text('condiciones')->nullable();
            }

            if (!Schema::hasColumn('proformas', 'descuento')) {
                $table->decimal('descuento', 18, 2)->default(0);
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * Deliberadamente NO se eliminan las columnas: son datos reales de clientes y un
     * rollback en Railway provocaría pérdida de información (RUC, teléfono, dirección,
     * condiciones). Si se necesita revertir en local: 'php artisan migrate:fresh'.
     */
    public function down(): void
    {
        // Sin operación: ver nota de seguridad de datos en el docblock de la clase.
    }
};
