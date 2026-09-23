<?php

namespace Tests\Feature;

use App\Models\Proforma;
use App\Models\ProformaDetalle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pruebas de integración del endpoint GET /proformas/{id}/pdf
 * (CalculadoraController::generarPDFPorId). Usan la base SQLite en memoria
 * configurada en phpunit.xml.
 */
class ProformaPdfTest extends TestCase
{
    use RefreshDatabase;

    private function crearProforma(array $detalles = []): Proforma
    {
        $proforma = Proforma::create([
            'codigo_proforma' => 'PROF-2026-0042',
            'cliente' => 'Constructora X',
            'fecha_emision' => '2026-09-22',
            'subtotal' => 3000,
            'impuesto' => 0,
            'total' => 3000,
            'estado' => 'Borrador',
            'moneda' => 'C$',
        ]);

        foreach ($detalles as $orden => $detalle) {
            ProformaDetalle::create([
                'proforma_id' => $proforma->id,
                'orden' => $orden + 1,
                'descripcion' => $detalle['descripcion'],
                'cantidad' => $detalle['cantidad'] ?? 1,
                'precio_unitario' => $detalle['precio_unitario'] ?? 0,
                'subtotal' => $detalle['subtotal'] ?? 0,
            ]);
        }

        return $proforma;
    }

    public function test_genera_el_pdf_de_una_proforma_guardada(): void
    {
        $proforma = $this->crearProforma([
            ['descripcion' => "Sistema de riego\n• Sección Fija: 2x2", 'cantidad' => 2, 'precio_unitario' => 1500, 'subtotal' => 3000],
        ]);

        $respuesta = $this->get(route('proformas.pdf', $proforma->id));

        $respuesta->assertOk();
        $respuesta->assertHeader('content-type', 'application/pdf');
        $respuesta->assertHeader('content-disposition', 'inline; filename=Prof-ready-0042.pdf');
        $this->assertStringStartsWith('%PDF', $respuesta->getContent());
    }

    public function test_genera_el_pdf_de_una_proforma_sin_detalles(): void
    {
        $proforma = $this->crearProforma();

        $this->get(route('proformas.pdf', $proforma->id))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_devuelve_404_si_la_proforma_no_existe(): void
    {
        $this->get(route('proformas.pdf', 999999))->assertNotFound();
    }

    public function test_el_historial_lista_las_proformas_registradas(): void
    {
        $this->crearProforma();

        $this->get(route('proformas.index'))
            ->assertOk()
            ->assertSee('PROF-2026-0042')
            ->assertSee('Constructora X');
    }

    public function test_la_pantalla_de_edicion_carga_la_proforma_y_sus_detalles(): void
    {
        $proforma = $this->crearProforma([
            ['descripcion' => "Servicio uno\nDetalle", 'cantidad' => 3, 'precio_unitario' => 100, 'subtotal' => 300],
        ]);

        $this->get(route('proformas.edit', $proforma->id))
            ->assertOk()
            ->assertSee('PROF-2026-0042');
    }
}
