<?php

namespace Tests\Feature;

use App\Services\ContadorProformaService;
use RuntimeException;
use Tests\TestCase;

/**
 * Manejo estricto de excepciones del servicio de numeración.
 *
 * Estas pruebas NO dependen de Firebase: apuntan el servicio a un puerto donde no
 * hay nada escuchando, así que el fallo de conexión es determinista y rápido.
 * El camino feliz (GET/PATCH reales y confirmación del incremento) se validó contra
 * un nodo temporal del RTDB durante la verificación de la Fase 2.
 */
class ContadorProformaServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Puerto sin servicio: cualquier petición falla al conectar.
        config(['services.firebase.url' => 'http://127.0.0.1:59999']);
    }

    public function test_obtener_el_contador_lanza_excepcion_si_no_hay_conexion(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('No se pudo conectar con el servicio de numeración (Firebase)');

        app(ContadorProformaService::class)->obtenerContadorActual();
    }

    public function test_incrementar_el_contador_lanza_excepcion_si_no_hay_conexion(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('No se pudo escribir el contador en el servicio de numeración (Firebase)');

        app(ContadorProformaService::class)->incrementarContador(7656);
    }
}
