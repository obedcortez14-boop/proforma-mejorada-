<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
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
 *  - incrementarContador() CONFIRMA la escritura (PUT condicional con 'If-Match') antes
 *    de retornar; si Firebase no confirma el nuevo valor, se lanza excepción y la
 *    proforma no se guarda (evita folios quemados sin documento).
 *  - incrementarContador() usa ESCRITURA CONDICIONAL ('If-Match' con el ETag de la
 *    lectura) para que la reserva del folio sea atómica frente a otros procesos y
 *    resolver las condiciones de carrera (H-01).
 *  - Si FIREBASE_SECRET está configurado, todas las peticiones se autentican con el
 *    parámetro 'auth' para no depender de reglas públicas del RTDB (H-16).
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

    /** Intentos de escritura condicional (compare-and-swap) antes de rendirse. */
    private const MAX_INTENTOS_CAS = 3;

    /** Parámetro de autenticación del Realtime Database (database secret / token). */
    private const PARAMETRO_AUTH = 'auth';

    /**
     * ETag devuelto por la última lectura: se reenvía como 'If-Match' en la escritura
     * condicional, de modo que el incremento sea atómico frente a otros escritores.
     */
    private ?string $etagActual = null;

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

        // Se conserva el ETag para que incrementarContador() pueda escribir con
        // 'If-Match' (compare-and-swap) sin pisar el trabajo de otro proceso.
        $this->etagActual = $respuesta['etag'];

        return (int) $valor;
    }

    /**
     * Incrementa el correlativo en Firebase con ESCRITURA CONDICIONAL y VERIFICA que
     * el cambio se aplicó antes de retornar.
     *
     * Por qué If-Match (H-01): entre la lectura y esta escritura otro proceso puede
     * haber movido el contador. Enviando el ETag de la lectura, Firebase rechaza la
     * escritura con HTTP 412 si el valor cambió, y aquí se resuelve:
     *
     *  - Si el contador fresco ya quedó en nuestro folio+1 (o más adelante), el folio
     *    reservado sigue siendo único en la base de datos: no hay nada que reparar.
     *  - Si el contador quedó por detrás (p. ej. reinicio manual), se reenvía la
     *    escritura condicional con el ETag nuevo (hasta MAX_INTENTOS_CAS).
     *
     * Nota técnica: Firebase solo soporta 'if-match' en PUT/DELETE, de modo que la
     * escritura condicional se hace con PUT sobre el nodo del contador.
     *
     * @param  int  $valorActual  Valor leído con obtenerContadorActual().
     * @return int  Nuevo valor del contador, ya confirmado por Firebase.
     *
     * @throws RuntimeException si la escritura falla, el HTTP no es 200/412, la
     *                          confirmación no coincide o se agotan los intentos.
     */
    public function incrementarContador(int $valorActual): int
    {
        $nuevoValor = $valorActual + 1;

        for ($intento = 1; $intento <= self::MAX_INTENTOS_CAS; $intento++) {
            // Firebase solo admite 'If-Match' en PUT/DELETE (no en PATCH/POST), por eso la
            // reserva del folio se hace con PUT sobre el nodo, cuyo único contenido es el
            // contador (el cuerpo conserva la misma forma {"contador_pdf": N}).
            $respuesta = $this->peticion('PUT', [self::CLAVE_CONTADOR => $nuevoValor], $this->etagActual);

            if ($respuesta['fallo']) {
                throw new RuntimeException(
                    'No se pudo escribir el contador en el servicio de numeración (Firebase): ' . $respuesta['errorCurl']
                );
            }

            if ($respuesta['codigoHttp'] === 200) {
                return $this->confirmarIncremento($respuesta['cuerpo'], $nuevoValor);
            }

            if ($respuesta['codigoHttp'] !== 412) {
                throw new RuntimeException(
                    'El servicio de numeración rechazó el incremento del contador (HTTP ' . $respuesta['codigoHttp']
                    . '). Cuerpo: ' . $this->recortar($respuesta['cuerpo'])
                );
            }

            // 412 Precondition Failed: el contador cambió entre la lectura y la escritura.
            $valorFresco = $this->obtenerContadorActual();

            if ($valorFresco >= $nuevoValor) {
                // Otro proceso dejó el contador en nuestro folio+1 o más adelante: el folio
                // reservado sigue siendo único en la BD, así que no hay nada que reparar.
                Log::warning('Contador de Firebase adelantado por otro proceso; el folio reservado se mantiene.', [
                    'folio'           => $valorActual,
                    'contador_actual' => $valorFresco,
                ]);

                return $nuevoValor;
            }

            Log::warning('Reintentando escritura condicional del contador de Firebase.', [
                'folio'           => $valorActual,
                'contador_actual' => $valorFresco,
                'intento'         => $intento,
            ]);
        }

        throw new RuntimeException(
            'No se pudo confirmar el incremento del contador (posible condición de carrera): '
            . self::MAX_INTENTOS_CAS . ' intentos agotados.'
        );
    }

    /**
     * Comprueba que Firebase devolvió exactamente el valor escrito.
     *
     * @throws RuntimeException si el valor confirmado no coincide con lo enviado.
     */
    private function confirmarIncremento(string $cuerpo, int $nuevoValor): int
    {
        $datos      = json_decode($cuerpo, true);
        $confirmado = is_array($datos) ? ($datos[self::CLAVE_CONTADOR] ?? null) : null;

        if (!is_numeric($confirmado) || (int) $confirmado !== $nuevoValor) {
            throw new RuntimeException(
                'El incremento del contador no quedó confirmado por Firebase (esperado ' . $nuevoValor
                . ', recibido ' . $this->recortar($cuerpo) . ').'
            );
        }

        return $nuevoValor;
    }

    /**
     * Ejecuta la petición HTTP contra el nodo del contador.
     *
     * @param  string|null  $etag  Si se envía, la escritura se condiciona con
     *                             'If-Match' (compare-and-swap, H-01).
     * @return array{fallo: bool, codigoHttp: int, cuerpo: string, errorCurl: string, etag: string|null}
     */
    private function peticion(string $metodo, array $datos = [], ?string $etag = null): array
    {
        // Firebase SOLO devuelve el ETag si se pide explícitamente con este header.
        $cabeceras = ['X-Firebase-ETag: true'];

        if (is_string($etag) && $etag !== '') {
            $cabeceras[] = 'If-Match: ' . $etag;
        }

        if ($metodo !== 'GET') {
            $cabeceras[] = 'Content-Type: application/json';
        }

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $this->urlContador());
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, true); // necesario para leer el ETag de la respuesta
        curl_setopt($ch, CURLOPT_TIMEOUT, self::TIMEOUT_SEGUNDOS);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, self::TIMEOUT_SEGUNDOS);
        // No se usa CURLOPT_SSL_VERIFYPEER => false en ningún caso: la verificación
        // del certificado TLS es obligatoria.

        if ($cabeceras !== []) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $cabeceras);
        }

        if ($metodo !== 'GET') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $metodo);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($datos));
        }

        $bruto          = curl_exec($ch);
        $errorCurl      = curl_error($ch);
        $codigoHttp     = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $largoCabeceras = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        curl_close($ch);

        if (!is_string($bruto)) {
            return [
                'fallo'      => true,
                'codigoHttp' => $codigoHttp,
                'cuerpo'     => '',
                'errorCurl'  => $errorCurl,
                'etag'       => null,
            ];
        }

        $cabecerasTexto = $largoCabeceras > 0 ? substr($bruto, 0, $largoCabeceras) : '';
        $cuerpo         = $largoCabeceras > 0 ? substr($bruto, $largoCabeceras) : $bruto;

        return [
            'fallo'      => false,
            'codigoHttp' => $codigoHttp,
            'cuerpo'     => $cuerpo,
            'errorCurl'  => $errorCurl,
            'etag'       => $this->extraerEtag($cabecerasTexto),
        ];
    }

    /** Extrae el ETag de las cabeceras de respuesta (Firebase lo devuelve entre comillas). */
    private function extraerEtag(string $cabeceras): ?string
    {
        if (preg_match('/^ETag:\s*(.+)$/mi', $cabeceras, $coincidencias) === 1) {
            return trim($coincidencias[1]);
        }

        return null;
    }

    /**
     * URL completa del nodo del contador.
     *
     * Si hay un secreto configurado (FIREBASE_SECRET) se añade el parámetro 'auth'
     * para no depender de reglas públicas del Realtime Database (H-16).
     */
    private function urlContador(): string
    {
        $urlBase = trim((string) config('services.firebase.url'));
        $urlBase = $urlBase !== '' ? rtrim($urlBase, '/') : self::URL_POR_DEFECTO;

        $url = $urlBase . '/' . self::NODO_CONTADOR;

        $secreto = trim((string) config('services.firebase.secret'));

        if ($secreto !== '') {
            $url .= '?' . self::PARAMETRO_AUTH . '=' . rawurlencode($secreto);
        }

        return $url;
    }

    /** Recorta un texto para poder incluirlo con seguridad en mensajes y logs. */
    private function recortar(string $texto, int $maximo = 200): string
    {
        $limpio = trim($texto);

        return $limpio === '' ? '(vacío)' : mb_substr($limpio, 0, $maximo);
    }
}
