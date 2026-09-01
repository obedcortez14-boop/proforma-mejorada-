<?php

if (!function_exists('formatDescripcionProforma')) {
    /**
     * Formatea la descripción de un servicio de la proforma en HTML:
     *  - 1ª línea: Título principal en negrita (<strong class="font-bold text-gray-900 block mb-1">).
     *  - Líneas con viñeta (•, -, –, *) y/o clave antes de ":" → clave en negrita (<b>• Sección Fija:</b> valor).
     *  - Saltos de línea (\n) convertidos a <br>.
     * El texto del usuario se escapa; el resultado es seguro para imprimir con {!! !!}.
     */
    function formatDescripcionProforma(?string $texto): string
    {
        return \App\Support\DescripcionProforma::formatDescripcionProforma($texto);
    }
}
