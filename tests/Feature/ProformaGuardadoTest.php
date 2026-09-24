<?php

namespace Tests\Feature;

use App\Models\Proforma;
use App\Models\ProformaDetalle;
use App\Services\ContadorProformaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

/**
 * Flujo comercial completo contra la SQLite local, con el servicio de numeración
 * MOCKEADO: las pruebas NUNCA golpean la Firebase real (no consumen folios ni
 * dependen de la red) y verifican que el controlador consuma el servicio.
 *
 * Cubre: renderizado de la calculadora (contador y alerta de error de conexión),
 * creación de proforma con reserva de folio y comportamiento ante el fallo de
 * numeración. La actualización se cubre en GapsConocidosTest.
 */
class ProformaGuardadoTest extends TestCase
{
    use RefreshDatabase;

    /** Payload equivalente al que envía la calculadora (welcome.blade.php). */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'correo'             => 'cliente.prueba@example.com',
            'cliente'            => 'Constructora Prueba Local',
            'contacto'           => 'Ing. Ana Perez',
            'ruc'                => 'J0310000008604',
            'telefono'           => '84940343',
            'direccion_proyecto' => '4 Calle Noreste, Managua',
            'condiciones'        => "Vigencia: 30 dias.\nAnticipo del 50%.",
            'subtotal_val'       => '3000',
            'descuento_val'      => '100',
            'iva_val'            => '435',
            'total_val'          => '3335',
            'moneda_simbolo'     => '$',
            'responsable_nombre' => 'Jammy Silva',
            'estado'             => 'Borrador',
            'fecha_emision'      => '2026-09-23',
            'items'              => [
                ['titulo' => '1. Sistema de riego', 'desc' => 'Seccion fija 2x2', 'cant' => '2', 'precio' => '1500'],
            ],
        ], $overrides);
    }

    /** Simula un contador sano: lee $actual y confirma $actual + 1. */
    private function simularContadorSano(int $actual): void
    {
        $this->mock(ContadorProformaService::class, function ($mock) use ($actual) {
            $mock->shouldReceive('obtenerContadorActual')->andReturn($actual);
            $mock->shouldReceive('incrementarContador')->with($actual)->andReturn($actual + 1);
        });
    }

    public function test_la_pantalla_principal_muestra_el_contador_que_entrega_el_servicio(): void
    {
        $this->simularContadorSano(7656);

        $this->get('/')
            ->assertOk()
            ->assertSee('7656')
            ->assertDontSee('No se pudo conectar con el servicio de numeración');
    }

    public function test_la_pantalla_principal_avisa_si_el_servicio_de_numeracion_falla(): void
    {
        $this->mock(ContadorProformaService::class, function ($mock) {
            $mock->shouldReceive('obtenerContadorActual')
                ->andThrow(new RuntimeException('sin conexion con Firebase'));
        });

        $this->get('/')
            ->assertOk()
            ->assertSee('No se pudo conectar con el servicio de numeración. El número mostrado es provisional.')
            ->assertSee('0001');
    }

    public function test_crea_una_proforma_con_el_folio_del_contador_y_redirige_al_pdf(): void
    {
        $this->simularContadorSano(7656);

        $respuesta = $this->post(route('pdf.generar'), $this->payload());

        $proforma = Proforma::with('detalles')->latest('id')->firstOrFail();

        // PRG (H-05): la creación responde con una REDIRECCIÓN al PDF por id, nunca con el
        // PDF directo; así un F5 repite el GET y no reenvía el formulario.
        $respuesta->assertRedirect(route('proformas.pdf', $proforma->id));

        $this->assertSame('PROF-'.date('Y').'-7656', $proforma->codigo_proforma);
        $this->assertSame('Constructora Prueba Local', $proforma->cliente);
        $this->assertSame('Jammy Silva', $proforma->vendedor);
        $this->assertSame('J0310000008604', $proforma->ruc_cedula);
        $this->assertSame('84940343', $proforma->telefono);
        $this->assertSame('4 Calle Noreste, Managua', $proforma->direccion_proyecto);
        $this->assertSame('cliente.prueba@example.com', $proforma->correo);
        $this->assertSame('$', $proforma->moneda);
        $this->assertStringContainsString('Vigencia', (string) $proforma->condiciones);
        $this->assertEqualsWithDelta(100.0, (float) $proforma->descuento, 0.01);
        $this->assertEqualsWithDelta(3335.0, (float) $proforma->total, 0.01);

        $this->assertCount(1, $proforma->detalles);
        $this->assertSame(1, (int) $proforma->detalles->first()->orden);
        $this->assertStringContainsString('Sistema de riego', (string) $proforma->detalles->first()->descripcion);

        // Siguiendo la redirección se obtiene el PDF real (el mismo documento de antes).
        $this->get(route('proformas.pdf', $proforma->id))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_si_el_servicio_de_numeracion_falla_no_guarda_nada_y_muestra_el_error_amigable(): void
    {
        $this->mock(ContadorProformaService::class, function ($mock) {
            $mock->shouldReceive('obtenerContadorActual')
                ->andThrow(new RuntimeException('No se pudo conectar con el servicio de numeración (Firebase): timeout'));
        });

        $respuesta = $this->post(route('pdf.generar'), $this->payload());

        // El catch del controlador ya no usa dd(): redirige con el mensaje amigable
        // y la transacción queda revertida, así que no se persiste nada.
        $respuesta->assertRedirect();
        $respuesta->assertSessionHasErrors('proforma');

        $this->assertSame(0, Proforma::count());
        $this->assertSame(0, ProformaDetalle::count());
    }

    public function test_el_refresco_repetido_del_pdf_no_duplica_la_proforma_ni_consume_otro_folio(): void
    {
        // 'once()' en el mock hace FALLAR la prueba si el contador se incrementa dos veces.
        $this->mock(ContadorProformaService::class, function ($mock) {
            $mock->shouldReceive('obtenerContadorActual')->once()->andReturn(7656);
            $mock->shouldReceive('incrementarContador')->once()->with(7656)->andReturn(7657);
        });

        $respuesta = $this->post(route('pdf.generar'), $this->payload());
        $respuesta->assertRedirect();

        $urlPdf = $respuesta->headers->get('Location');

        // Simula el F5 (varias veces) sobre la pestaña del PDF: sólo se repite el GET.
        $this->get($urlPdf)->assertOk();
        $this->get($urlPdf)->assertOk();
        $this->get($urlPdf)->assertOk();

        $this->assertSame(1, Proforma::count());
        $this->assertSame(1, ProformaDetalle::count());
    }

    public function test_si_el_folio_ya_existe_el_guardado_falla_sin_incrementar_el_contador(): void
    {
        // El contador entregó un folio ya emitido: el INSERT debe romper contra el índice
        // UNIQUE y la transacción hace rollback (H-04: no se quema ningún folio).
        Proforma::create([
            'codigo_proforma' => 'PROF-'.date('Y').'-7656',
            'cliente'         => 'Folio ya emitido',
            'fecha_emision'   => '2026-09-23',
            'estado'          => 'Borrador',
        ]);

        $this->mock(ContadorProformaService::class, function ($mock) {
            $mock->shouldReceive('obtenerContadorActual')->andReturn(7656);
            // Como el guardado falla, el contador NUNCA debe consumirse.
            $mock->shouldReceive('incrementarContador')->never();
        });

        $respuesta = $this->post(route('pdf.generar'), $this->payload());

        $respuesta->assertRedirect();
        $respuesta->assertSessionHasErrors('proforma');

        $this->assertSame(1, Proforma::count());
        $this->assertSame(0, ProformaDetalle::count());
    }
}
