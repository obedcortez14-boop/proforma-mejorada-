<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Saneamiento de datos + unicidad del folio 'proformas.codigo_proforma'.
 *
 * Origen del problema: el folio (ej. "PROF-2026-0042") se genera leyendo e
 * incrementando un contador en Firebase sin operación atómica, y la tabla sólo
 * tenía la llave primaria sobre 'id'. Nada impedía guardar folios repetidos, por
 * eso quedaron 7 pares históricos duplicados.
 *
 * Esta migración es IDEMPOTENTE (puede correr varias veces sin efectos extra) y
 * hace tres cosas:
 *  1. Normaliza a NULL los folios vacíos ('' o espacios): varios '' chocarían
 *     contra el índice único y no representan un folio real.
 *  2. Renombra los folios repetidos agregando sufijos '-A', '-B', ... '-AA'. El
 *     PRIMER registro de cada folio (el de menor 'id') conserva el original y los
 *     siguientes se renombran; si el sufijo calculado ya estuviera ocupado se
 *     prueba el siguiente hasta encontrar uno libre.
 *  3. Crea el índice UNIQUE sobre 'codigo_proforma' (sólo si no existe ya un índice
 *     único formado exactamente por esa columna).
 *
 * Funciona igual en PostgreSQL (Railway), MySQL y SQLite (pruebas locales).
 */
return new class extends Migration
{
    /** Nombre por defecto que Laravel asigna al índice único de esta migración. */
    private const INDICE_UNICO = 'proformas_codigo_proforma_unique';

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $this->normalizarFoliosVacios();
        $this->sanearFoliosDuplicados();
        $this->asegurarIndiceUnico();
    }

    /**
     * Reverse the migrations.
     *
     * Los sufijos '-A', '-B'... NO se revierten: son folios ya impresos y
     * entregados al cliente, por lo que deshacerlos rompería la trazabilidad.
     * Sólo se elimina el índice único creado por esta migración.
     */
    public function down(): void
    {
        $existeIndicePropio = collect(Schema::getIndexes('proformas'))
            ->contains(fn ($indice) => ($indice['name'] ?? '') === self::INDICE_UNICO);

        if ($existeIndicePropio) {
            Schema::table('proformas', function (Blueprint $table) {
                $table->dropUnique(self::INDICE_UNICO);
            });
        }
    }

    /** Convierte los folios vacíos o en blanco en NULL ("sin folio"). */
    private function normalizarFoliosVacios(): void
    {
        DB::table('proformas')
            ->whereNotNull('codigo_proforma')
            ->whereRaw("TRIM(COALESCE(codigo_proforma, '')) = ''")
            ->update(['codigo_proforma' => null]);
    }

    /** Renombra el folio de todos los repetidos salvo el primero de cada grupo. */
    private function sanearFoliosDuplicados(): void
    {
        $foliosDuplicados = DB::table('proformas')
            ->select('codigo_proforma')
            ->whereNotNull('codigo_proforma')
            ->groupBy('codigo_proforma')
            ->havingRaw('COUNT(*) > 1')
            ->orderBy('codigo_proforma')
            ->pluck('codigo_proforma');

        foreach ($foliosDuplicados as $folio) {
            $idsRepetidos = DB::table('proformas')
                ->where('codigo_proforma', $folio)
                ->orderBy('id')
                ->pluck('id')
                ->slice(1) // el primer registro (menor 'id') conserva el folio original
                ->values();

            foreach ($idsRepetidos as $posicion => $id) {
                DB::table('proformas')
                    ->where('id', $id)
                    ->update(['codigo_proforma' => $this->folioLibre($folio, (int) $posicion)]);
            }
        }
    }

    /** Devuelve "<folio>-A", "<folio>-B"... salteando los sufijos ya ocupados. */
    private function folioLibre(string $folio, int $posicion): string
    {
        while (true) {
            $candidato = $folio . '-' . $this->letrasSufijo($posicion);

            if (!DB::table('proformas')->where('codigo_proforma', $candidato)->exists()) {
                return $candidato;
            }

            $posicion++;
        }
    }

    /** 0 => 'A', 25 => 'Z', 26 => 'AA' (numeración estilo hoja de cálculo). */
    private function letrasSufijo(int $posicion): string
    {
        $letras   = '';
        $posicion = max(0, $posicion);

        do {
            $letras   = chr(65 + ($posicion % 26)) . $letras;
            $posicion = intdiv($posicion, 26) - 1;
        } while ($posicion >= 0);

        return $letras;
    }

    /** Crea el índice único de 'codigo_proforma' si aún no existe. */
    private function asegurarIndiceUnico(): void
    {
        if ($this->existeIndiceUnicoCodigoProforma()) {
            return;
        }

        Schema::table('proformas', function (Blueprint $table) {
            $table->unique('codigo_proforma');
        });
    }

    /** Indica si ya existe un índice único cuyo único campo es 'codigo_proforma'. */
    private function existeIndiceUnicoCodigoProforma(): bool
    {
        return collect(Schema::getIndexes('proformas'))
            ->contains(fn ($indice) => ($indice['unique'] ?? false)
                && ($indice['columns'] ?? []) === ['codigo_proforma']);
    }
};
