<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

/**
 * Pruebas de integración de la vista del PDF (resources/views/factura.blade.php).
 * Verifican el renderizado completo, el escapado de datos del usuario y los
 * valores por defecto de la plantilla.
 */
class FacturaViewTest extends TestCase
{
    /** Datos mínimos que envía CalculadoraController::descargarPdfMapeado(). */
    private function datos(array $overrides = []): array
    {
        return array_merge([
            'logo' => null,
            'nuevoContador' => '0007',
            'cliente' => 'Constructora X',
            'contacto' => 'Ana Perez',
            'ruc' => '001-123456-0001Y',
            'telefono' => '8888-8888',
            'correo' => 'ana@example.com',
            'direccion_proyecto' => 'Managua',
            'items' => [
                ['desc' => "Sistema de riego\n• Sección Fija: estructura 2x2", 'cant' => 2, 'precio' => 1500.0],
            ],
            'subtotal' => 3000.0,
            'descuento' => 100.0,
            'iva' => 435.0,
            'total' => 3335.0,
            'moneda_simbolo' => 'C$',
            'responsable_nombre' => 'Jammy Silva',
            'responsable_cargo' => 'Arquitecta Coordinadora',
            'responsable_tel' => '8588-5337',
            'fecha' => '22/09/2026',
            'condiciones' => null,
        ], $overrides);
    }

    public function test_renderiza_encabezado_totales_y_firma(): void
    {
        $html = view('factura', $this->datos())->render();

        $this->assertStringContainsString('PROFORMA NO.', $html);
        $this->assertStringContainsString('0007', $html);
        $this->assertStringContainsString('FECHA: 22/09/2026', $html);
        $this->assertStringContainsString('Constructora X', $html);
        $this->assertStringContainsString('C$ 3,000.00', $html);
        $this->assertStringContainsString('- C$ 100.00', $html);
        $this->assertStringContainsString('C$ 3,335.00', $html);
        $this->assertStringContainsString('Jammy Silva', $html);
    }

    public function test_renderiza_la_descripcion_con_formato_y_sin_html_del_usuario(): void
    {
        $html = view('factura', $this->datos([
            'items' => [
                [
                    'desc' => "Riego <script>alert('x')</script>\n• Sección Fija: 2x2",
                    'cant' => 1,
                    'precio' => 10.0,
                ],
            ],
        ]))->render();

        // Título y clave en negrita (formato enriquecido del helper global)
        $this->assertStringContainsString('desc-titulo', $html);
        $this->assertStringContainsString('desc-clave', $html);
        // El HTML del usuario queda escapado
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }

    public function test_muestra_las_condiciones_por_defecto_cuando_no_se_envian(): void
    {
        $html = view('factura', $this->datos(['condiciones' => null]))->render();

        $this->assertStringContainsString('Vigencia de la cotización', $html);
    }

    public function test_muestra_las_condiciones_del_usuario_escapadas(): void
    {
        $html = view('factura', $this->datos([
            'condiciones' => '• Pago 50%<script>alert(1)</script>',
        ]))->render();

        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('Pago 50%', $html);
        $this->assertStringNotContainsString('Vigencia de la cotización', $html);
    }

    public function test_renderiza_una_tabla_vacia_sin_items(): void
    {
        $html = view('factura', $this->datos([
            'items' => [],
            'subtotal' => 0.0,
            'descuento' => 0.0,
            'iva' => 0.0,
            'total' => 0.0,
        ]))->render();

        $this->assertStringContainsString('Descripción Detallada', $html);
        $this->assertStringContainsString('TOTAL A PAGAR', $html);
        // Sin descuento ni IVA no deben aparecer esas filas
        $this->assertStringNotContainsString('Descuento', $html);
        $this->assertStringNotContainsString('IVA (15%)', $html);
    }

    public function test_el_helper_global_esta_disponible_durante_el_render_de_blade(): void
    {
        $this->assertTrue(function_exists('formatDescripcionProforma'));

        $compilado = Blade::render('{!! formatDescripcionProforma($texto) !!}', [
            'texto' => "Titulo\nClave: valor",
        ]);

        $this->assertStringContainsString('desc-titulo', $compilado);
        $this->assertStringContainsString('desc-clave', $compilado);
    }
}
