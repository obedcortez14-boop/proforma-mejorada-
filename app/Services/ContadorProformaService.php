<?php

namespace App\Services;

use RuntimeException;

/**
 * Servicio centralizado de numeración de proformas (Firebase Realtime Database).
 *
 * Concentra TODA la interacción con el RTDB. Antes había cURL duplicado en
 * CalculadoraController::generarPDF() y dentro de resources/views/welcome.blade.php
 * (con CURLOPT_SSL_VERIFYPEER desactivado). Ahora:
 *
 *  - La URL vive en config('services.firebase.url') (variable FIREBASE_URL).
 *  - Timeout estricto de 5 s (conexión y respuesta completa) en cada petición.
 *  - La validación TLS del certificado queda SIEMPRE activa.
 *  - Cada respuesta se valida: error de cURL, código HTTP y contenido JSON.
 *  - incrementarContador() CONFIRMA la escritura (PATCH) antes de retornar; si
 *    Firebase no confirma el nuevo valor, se lanza excepción y la proforma no se
 *    guarda (evita folios quemados sin documento).
 *
 * Las vistas NO deben usar este servicio: el controlador obtiene el contador y lo
 * entrega a la vista ya formateado.
 */
class ContadorProformaService
{
    /** Timeout (conexión y respuesta) de cada petición a Firebase, en segundos. */
    public const TIMEOUT_SEGUNDOS = 5;

    /** Nodo del RTDB donde vive el correlativo de proformas. */
    private const NODO_CONTADOR = 'contador_pdf.json';

    /** Clave interna del correlativo dentro del nodo. */
    private const CLAVE_CONTADOR = 'contador_pdf';

    /** URL de respaldo si FIREBASE_URL quedara vacía. */
    private const URL_POR_DEFECTO = 'https://proforma-ready-default-rtdb.firebaseio.com';

    /**
     * Lee el correlativo actual desde Firebase.
     *
     * El nodo puede responder {"contador_pdf": 7656} o un escalar suelto: ambos
     * formatos se aceptan, pero un valor no numérico es un error.
     *
     * @throws RuntimeException si no hay conexión, el HTTP no es 200, la respuesta
     *                          no es JSON válido o el valor devuelto no es numérico.
     */
    public function obtenerContadorActual(): int
    {
        $respuesta = $this->peticion('GET');

        if ($respuesta['fallo']) {
            throw new RuntimeException(
                'No se pudo conectar con el servicio de numeración (Firebase): ' . $respuesta['errorCurl']
            );
        }

        if ($respuesta['codigoHttp'] !== 200) {
            throw new RuntimeException(
                'El servicio de numeración (Firebase) respondió HTTP ' . $respuesta['codigoHttp']
                . ' al leer el contador. Cuerpo: ' . $this->recortar($respuesta['cuerpo'])
            );
        }

        $datos = json_decode($respuesta['cuerpo'], true);

        if (json_last_error() !== JSON_ERROR_NONE || $datos === null) {
            throw new RuntimeException(
                'La respuesta del servicio de numeración no es un JSON válido: ' . $this->recortar($respuesta['cuerpo'])
            );
        }

        // Se exige que el contador exista y sea numérico: interpretar un nodo vacío
        // como "1" (comportamiento anterior) generaba folios duplicados.
        $valor = is_array($datos) ? ($datos[self::CLAVE_CONTADOR] ?? null) : $datos;

        if (!is_numeric($valor)) {
            throw new RuntimeException(
                'El servicio de numeración no devolvió un contador numérico: ' . $this->recortar($respuesta['cuerpo'])
            );
        }

        return (int) $valor;
    }

    /**
     * Incrementa el correlativo en Firebase y VERIFICA que el cambio se aplicó.
     *
     * @param  int  $valorActual  Valor leído con obtenerContadorActual().
     * @return int  Nuevo valor del contador, ya confirmado por Firebase.
     *
     * @throws RuntimeException si la escritura falla, el HTTP no es 200 o la
     *                          confirmación devuelta no coincide con lo enviado.
     */
    public function incrementarContador(int $valorActual): int
    {
        $nuevoValor = $valorActual + 1;

        $respuesta = $this->peticion('PATCH', [self::CLAVE_CONTADOR => $nuevoValor]);

        if ($respuesta['fallo']) {
            throw new RuntimeException(
                'No se pudo escribir el contador en el servicio de numeración (Firebase): ' . $respuesta['errorCurl']
            );
        }

        if ($respuesta['codigoHttp'] !== 200) {
            throw new RuntimeException(
                'El servicio de numeración rechazó el incremento del contador (HTTP ' . $respuesta['codigoHttp']
                . '). Cuerpo: ' . $this->recortar($respuesta['cuerpo'])
            );
        }

        // Firebase devuelve el JSON escrito: si no coincide, el incremento NO se da
        // por bueno y se aborta antes de guardar la proforma.
        $datos      = json_decode($respuesta['cuerpo'], true);
        $confirmado = is_array($datos) ? ($datos[self::CLAVE_CONTADOR] ?? null) : null;

        if (!is_numeric($confirmado) || (int) $confirmado !== $nuevoValor) {
            throw new RuntimeException(
                'El incremento del contador no quedó confirmado por Firebase (esperado ' . $nuevoValor
                . ', recibido ' . $this->recortar($respuesta['cuerpo']) . ').'
            );
        }

        return $nuevoValor;
    }

    /**
     * Ejecuta la petición HTTP contra el nodo del contador.
     *
     * No se usa CURLOPT_SSL_VERIFYPEER => false en ningún caso: la verificación del
     * certificado TLS es obligatoria.
     *
     * @return array{fallo: bool, codigoHttp: int, cuerpo: string, errorCurl: string}
     */
    private function peticion(string $metodo, array $datos = []): array
    {
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $this->urlContador());
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, self::TIMEOUT_SEGUNDOS);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, self::TIMEOUT_SEGUNDOS);

        if ($metodo !== 'GET') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $metodo);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($datos));
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        }

        $cuerpo     = curl_exec($ch);
        $errorCurl  = curl_error($ch);
        $codigoHttp = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return [
            'fallo'      => $cuerpo === false,
            'codigoHttp' => $codigoHttp,
            'cuerpo'     => is_string($cuerpo) ? $cuerpo : '',
            'errorCurl'  => $errorCurl,
        ];
    }

    /** URL completa del nodo del contador. */
    private function urlContador(): string
    {
        $urlBase = trim((string) config('services.firebase.url'));
        $urlBase = $urlBase !== '' ? rtrim($urlBase, '/') : self::URL_POR_DEFECTO;

        return $urlBase . '/' . self::NODO_CONTADOR;
    }

    /** Recorta un texto para poder incluirlo con seguridad en mensajes y logs. */
    private function recortar(string $texto, int $maximo = 200): string
    {
        $limpio = trim($texto);

        return $limpio === '' ? '(vacío)' : mb_substr($limpio, 0, $maximo);
    }
}
