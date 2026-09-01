<?php

namespace App\Support;

/**
 * Formateador dinámico de descripciones complejas para las proformas.
 *
 * Reglas de formato (espejo exacto del parser JS de las vistas):
 *  - La PRIMERA línea con contenido se renderiza como TÍTULO principal en negrita:
 *    <strong class="font-bold text-gray-900 block mb-1">.
 *  - Las líneas que inician con viñeta (•, -, –, *) y/o contienen una "clave" corta
 *    seguida de dos puntos (ej: "• Sección Fija:", "Dimensiones Generales:") imprimen
 *    la clave en negrita: <b>• Sección Fija:</b> Estructura fabricada...
 *  - Los saltos de línea (\n) se convierten a <br>.
 *  - Todo el texto del usuario se escapa; solo se agregan etiquetas seguras
 *    (<strong>, <b>, <br>), por lo que el resultado es seguro para imprimir con {!! !!}.
 */
class DescripcionProforma
{
    /** Longitud máxima permitida para una "clave" antes de los dos puntos. */
    public const MAX_LONGITUD_CLAVE = 60;

    /**
     * Divide la descripción guardada en [título, detalle].
     * La primera línea es el título del servicio; el resto es el detalle.
     *
     * @return array{0: string, 1: string}
     */
    public static function tituloYDetalle(?string $texto): array
    {
        $lineas  = self::normalizarSaltos($texto);
        $titulo  = trim($lineas[0] ?? '');
        $detalle = count($lineas) > 1 ? trim(implode("\n", array_slice($lineas, 1))) : '';

        return [$titulo, $detalle];
    }

    /**
     * Convierte la descripción de un servicio en HTML con formato enriquecido:
     * título principal en negrita + subtítulos/claves en negrita + <br> por cada salto de línea.
     */
    public static function formatDescripcionProforma(?string $texto): string
    {
        $lineas  = self::normalizarSaltos($texto);
        $html    = '';
        $primera = true;

        foreach ($lineas as $linea) {
            $limpia = trim($linea);
            if ($limpia === '') {
                continue;
            }

            if ($primera) {
                // Título principal del servicio (siempre en negrita)
                $html .= '<strong class="font-bold text-gray-900 block mb-1 desc-titulo">' . e($limpia) . '</strong>';
                $primera = false;
                continue;
            }

            $html .= '<br>' . self::formatearLineaClave($limpia);
        }

        return $html;
    }

    /** Normaliza saltos de línea (\r\n, \r) y divide el texto por \n. */
    protected static function normalizarSaltos(?string $texto): array
    {
        return explode("\n", str_replace(["\r\n", "\r"], "\n", (string) $texto));
    }

    /**
     * Formatea una línea del detalle: si tiene el patrón "[viñeta] Clave: valor",
     * imprime la clave (con su viñeta) en negrita antes del valor.
     */
    protected static function formatearLineaClave(string $linea): string
    {
        if (preg_match('/^(\s*[•\-–*]\s*)?([^:\n]{1,' . self::MAX_LONGITUD_CLAVE . '}?):\s*(.*)$/u', $linea, $m)) {
            $vineta = trim($m[1] ?? '');
            $clave  = trim($m[2]);
            $resto  = trim($m[3]);

            // Evita falsos positivos tipo horas ("10:30 am"):
            // la clave debe contener al menos una letra.
            if ($clave !== '' && preg_match('/[A-Za-zÁÉÍÓÚáéíóúÑñÜü]/u', $clave)) {
                $html  = '<b class="font-bold text-gray-900 desc-clave">';
                $html .= $vineta !== '' ? e($vineta) . ' ' : '';
                $html .= e($clave) . ':</b>';

                if ($resto !== '') {
                    $html .= ' ' . e($resto);
                }

                return $html;
            }
        }

        // Línea normal (viñetas sin clave o texto libre se mantienen tal cual, escapados)
        return e($linea);
    }
}
