<?php

namespace Tests\Feature;

use App\Models\Proforma;
use App\Models\ProformaDetalle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Hallazgos del informe de QA y su estado tras la Fase 2:
 *
 *  1. Deriva de esquema (columnas que el controlador escribe sin migración):
 *     ✅ RESUELTO — ver test_el_esquema_migrado_incluye_las_columnas... y la
 *     migración 2026_09_23_010000_add_campos_cliente_to_proformas_table.
 *  2. dd() dentro de los catch (mataba el proceso de PHPUnit y filtraba rutas
 *     internas del servidor): ✅ RESUELTO — el catch ahora registra en el log y
 *     redirige con errores; por eso el test de actualización ya corre de verdad
 *     contra la SQLite local.
 *  3. Ausencia de autenticación/autorización en los endpoints de proformas:
 *     ⏳ PENDIENTE (Fase 3) — se documenta con una prueba de caracterización que
 *     fija el comportamiento ACTUAL; al agregar middleware("auth") + Policies hay
 *     que invertirla por assertRedirect(route("login")).
 */
class GapsConocidosTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Columnas que CalculadoraController escribe en 'proformas' pero que
     * ninguna migración del repositorio crea.
     */
    private const COLUMNAS_ESCRITAS_POR_EL_CONTROLADOR = [
        'vendedor',
        'ruc_cedula',
        'telefono',
        'direccion_proyecto',
        'condiciones',
        'descuento',
    ];

    /** Proforma mínima con un detalle, para ejercitar la edición. */
    private function crearProformaConDetalle(): Proforma
    {
        $proforma = Proforma::create([
            'codigo_proforma' => 'PROF-2026-0042',
            'cliente'         => 'Cliente Original',
            'fecha_emision'   => '2026-09-22',
            'subtotal'        => 1000,
            'impuesto'        => 0,
            'total'           => 1000,
            'estado'          => 'Borrador',
            'moneda'          => '$',
        ]);

        ProformaDetalle::create([
            'proforma_id'     => $proforma->id,
            'orden'           => 1,
            'descripcion'     => "1. Servicio original\nDetalle original",
            'cantidad'        => 1,
            'precio_unitario' => 1000,
            'subtotal'        => 1000,
        ]);

        return $proforma;
    }

    public function test_el_esquema_migrado_incluye_las_columnas_que_escribe_el_controlador(): void
    {
        $faltantes = collect(self::COLUMNAS_ESCRITAS_POR_EL_CONTROLADOR)
            ->reject(fn (string $columna) => Schema::hasColumn('proformas', $columna));

        $this->assertTrue(
            $faltantes->isEmpty(),
            'Falta(n) la(s) columna(s) ['.$faltantes->implode(', ').'] en "proformas": '
            .'POST /generar-pdf y PUT /proformas/{id} fallarían con SQLSTATE.'
        );
    }

    public function test_los_endpoints_de_proformas_siguen_sin_exigir_autenticacion(): void
    {
        // PRUEBA DE CARACTERIZACIÓN (deuda técnica de Fase 3): hoy cualquier visitante
        // anónimo puede listar (GET /proformas), editar (PUT /proformas/{id}) y descargar
        // (GET /proformas/{id}/pdf) todas las proformas, exponiendo datos de clientes
        // (RUC, teléfono, correo, direcciones y montos).
        //
        // Se fija el comportamiento ACTUAL para que el endurecimiento sea un cambio
        // consciente de Fase 3: al agregar middleware("auth") + Policies, ESTA PRUEBA
        // debe invertirse por assertRedirect(route("login")).
        foreach (['proformas.index', 'proformas.edit', 'proformas.pdf'] as $ruta) {
            $this->assertNotContains(
                'auth',
                app('router')->getRoutes()->getByName($ruta)->middleware(),
                'La ruta '.$ruta.' empezó a exigir autenticación: actualizar esta prueba de caracterización.'
            );
        }

        $this->get(route('proformas.index'))->assertOk();
    }

    public function test_actualizar_una_proforma_responde_ok(): void
    {
        // Antes estaba bloqueado por dos hallazgos ya resueltos: (1) el esquema no tenía
        // las columnas que escribe el controlador y (2) dd() dentro del catch mataba el
        // proceso de PHPUnit. Ahora la actualización completa corre contra SQLite.
        $proforma = $this->crearProformaConDetalle();

        $respuesta = $this->put(route('proformas.update', $proforma->id), [
            'correo'             => 'cliente.editado@example.com',
            'cliente'            => 'Cliente Editado',
            'contacto'           => 'Contacto Editado',
            'ruc'                => 'J0310000008604',
            'telefono'           => '84940343',
            'direccion_proyecto' => '4 Calle Noreste, Managua',
            'condiciones'        => "Vigencia: 30 dias.\nAnticipo del 50%.",
            'subtotal_val'       => '2000',
            'descuento_val'      => '150',
            'iva_val'            => '0',
            'total_val'          => '1850',
            'moneda_simbolo'     => 'C$',
            'responsable_nombre' => 'Jammy Silva',
            'estado'             => 'Enviada',
            'fecha_emision'      => '2026-09-23',
            'items'              => [
                ['titulo' => '1. Riego', 'desc' => 'Detalle nuevo', 'cant' => '2', 'precio' => '1000'],
            ],
        ]);

        // PRG (H-05): la edición redirige a la descarga del PDF (no devuelve el PDF directo).
        $respuesta->assertRedirect(route('proformas.pdf', $proforma->id));

        $this->get(route('proformas.pdf', $proforma->id))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $proforma->refresh();

        $this->assertSame('Cliente Editado', $proforma->cliente);
        $this->assertSame('Contacto Editado', $proforma->observaciones);
        $this->assertSame('J0310000008604', $proforma->ruc_cedula);
        $this->assertSame('84940343', $proforma->telefono);
        $this->assertSame('4 Calle Noreste, Managua', $proforma->direccion_proyecto);
        $this->assertSame('Jammy Silva', $proforma->vendedor);
        $this->assertSame('Enviada', $proforma->estado);
        $this->assertSame('cliente.editado@example.com', $proforma->correo);
        $this->assertSame('C$', $proforma->moneda);
        $this->assertStringContainsString('Vigencia', (string) $proforma->condiciones);
        $this->assertEqualsWithDelta(150.0, (float) $proforma->descuento, 0.01);
        $this->assertEqualsWithDelta(1850.0, (float) $proforma->total, 0.01);

        // Los detalles se reemplazan (delete + insert) manteniendo el orden secuencial.
        $detalles = ProformaDetalle::where('proforma_id', $proforma->id)->get();
        $this->assertCount(1, $detalles);
        $this->assertSame(1, (int) $detalles->first()->orden);
        $this->assertStringContainsString('Riego', (string) $detalles->first()->descripcion);
        $this->assertEqualsWithDelta(2000.0, (float) $detalles->first()->subtotal, 0.01);
    }
}
