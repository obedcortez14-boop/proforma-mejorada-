<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Agrega a 'proforma_detalles' la columna 'orden' (posición secuencial de cada
 * línea dentro de su proforma) y un índice sobre 'proforma_id'.
 *
 * Motivo: hasta ahora el orden de las líneas dependía del orden físico de la
 * tabla. Como el UPDATE borra y reinserta los detalles, PostgreSQL reutiliza
 * huecos en páginas tempranas del heap y el SELECT sin ORDER BY (Seq Scan)
 * devolvía las líneas desordenadas (ej: 5, 6, 1, 2, 3, 4).
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasColumn('proforma_detalles', 'orden')) {
            Schema::table('proforma_detalles', function (Blueprint $table) {
                $table->unsignedInteger('orden')->default(0);
            });
        }

        // Backfill: numera las líneas históricas por proforma usando el 'id'
        // actual como criterio provisional de orden (lo mejor disponible).
        // Implementado en PHP para que funcione igual en PostgreSQL, MySQL y SQLite
        // (SQLite es el motor que usan las pruebas automatizadas).
        $pendientes = DB::table('proforma_detalles')
            ->where('orden', 0)
            ->orderBy('proforma_id')
            ->orderBy('id')
            ->get(['id', 'proforma_id'])
            ->groupBy('proforma_id');

        foreach ($pendientes as $proformaId => $filas) {
            $posicion = (int) DB::table('proforma_detalles')
                ->where('proforma_id', $proformaId)
                ->max('orden');

            foreach ($filas->sortBy('id') as $fila) {
                DB::table('proforma_detalles')
                    ->where('id', $fila->id)
                    ->update(['orden' => ++$posicion]);
            }
        }

        $this->asegurarIndiceProformaId();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Solo se elimina el índice si lo creó esta migración (evita romper
        // índices implícitos de llaves foráneas en otros motores).
        $existeIndicePropio = collect(Schema::getIndexes('proforma_detalles'))
            ->contains(fn ($indice) => ($indice['name'] ?? '') === 'proforma_detalles_proforma_id_index');

        if ($existeIndicePropio) {
            Schema::table('proforma_detalles', function (Blueprint $table) {
                $table->dropIndex(['proforma_id']);
            });
        }

        if (Schema::hasColumn('proforma_detalles', 'orden')) {
            Schema::table('proforma_detalles', function (Blueprint $table) {
                $table->dropColumn('orden');
            });
        }
    }

    /**
     * Crea el índice de 'proforma_id' solo si no existe ya (la llave foránea
     * no lo crea automáticamente en PostgreSQL). Se consulta el esquema con
     * Schema::getIndexes() para que funcione en cualquier motor de base de datos.
     */
    private function asegurarIndiceProformaId(): void
    {
        if ($this->existeIndiceProformaId()) {
            return;
        }

        Schema::table('proforma_detalles', function (Blueprint $table) {
            $table->index('proforma_id');
        });
    }

    /** Indica si 'proforma_id' ya está indexado en 'proforma_detalles'. */
    private function existeIndiceProformaId(): bool
    {
        return collect(Schema::getIndexes('proforma_detalles'))
            ->contains(fn ($indice) => in_array('proforma_id', $indice['columns'] ?? [], true));
    }
};
