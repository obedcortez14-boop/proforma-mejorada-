<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Log;
use App\Models\Proforma;
use App\Models\ProformaDetalle;
use App\Services\ContadorProformaService;
use Exception;

class CalculadoraController extends Controller
{
    /** Folio provisional que se muestra si el servicio de numeración no responde. */
    private const CONTADOR_PROVISIONAL = 1;

    /**
     * El servicio de numeración se inyecta por constructor: es la ÚNICA vía de
     * acceso a Firebase (ni el controlador ni la vista usan cURL directo).
     */
    public function __construct(private readonly ContadorProformaService $contadorService)
    {
    }

    // =====================================================
    // 0. PANTALLA INICIAL (CALCULADORA)
    // =====================================================
    /**
     * Renderiza la calculadora con el correlativo que entrega el servicio.
     * Si Firebase no responde, la pantalla se muestra igual con el folio provisional
     * y el mensaje viaja a la vista (misma alerta de la Fase 1).
     */
    public function inicio()
    {
        try {
            $contadorActual = $this->contadorService->obtenerContadorActual();
            $errorContador  = null;
        } catch (Exception $e) {
            Log::error('No se pudo obtener el contador de proformas: ' . $e->getMessage());

            $contadorActual = self::CONTADOR_PROVISIONAL;
            $errorContador  = 'No se pudo conectar con el servicio de numeración. El número mostrado es provisional.';
        }

        return view('welcome', [
            'nuevoContador' => $this->formatearContador($contadorActual),
            'errorContador' => $errorContador,
        ]);
    }

    // =====================================================
    // 1. HISTORIAL DE PROFORMAS
    // =====================================================
    public function index()
    {
        $proformas = Proforma::with('detalles')->latest()->get();
        return view('proformas.index', compact('proformas'));
    }

    // =====================================================
    // 2. MOSTRAR PANTALLA DE EDICIÓN
    // =====================================================
    public function edit($id)
    {
        $proforma = Proforma::with('detalles')->findOrFail($id);
        return view('proformas.edit', compact('proforma'));
    }

    // =====================================================
    // 3. GUARDAR NUEVA PROFORMA Y GENERAR PDF
    // =====================================================
    public function generarPDF(Request $request)
    {
        // VALIDACIÓN: el correo es opcional (nullable), pero si se envía debe
        // ser una dirección de email válida y de máximo 255 caracteres.
        $request->validate([
            'correo' => ['nullable', 'email', 'max:255'],
        ]);

        // 1) Lectura del folio candidato FUERA de la transacción (es I/O de red: H-07).
        try {
            $valorActual   = $this->contadorService->obtenerContadorActual();
            $nuevoContador = $this->formatearContador($valorActual);
            $codigoFinal   = 'PROF-' . date('Y') . '-' . $nuevoContador;
        } catch (Exception $e) {
            Log::error('No se pudo consultar el contador de proformas: ' . $e->getMessage());

            return back()
                ->withInput()
                ->withErrors(['proforma' => 'No se pudo consultar el servicio de numeración. Intente nuevamente.']);
        }

        // 2) Persistencia en UNA transacción, sin I/O de red dentro.
        DB::beginTransaction();

        try {
            // CAPTURA DE FECHA DE EMISIÓN DESDE EL FORMULARIO
            $fechaEmisionInput = $request->input('fecha_emision') ?? date('Y-m-d');

            $proforma = new Proforma();
            $proforma->codigo_proforma = $codigoFinal;
            $proforma->cliente         = $request->input('cliente') ?? 'Consumidor Final';
            $proforma->fecha_emision   = $fechaEmisionInput;
            $proforma->subtotal        = (float) $request->input('subtotal_val', 0);
            $proforma->impuesto        = (float) $request->input('iva_val', 0);
            $proforma->total           = (float) $request->input('total_val', 0);
            $proforma->observaciones   = $request->input('contacto');
            $proforma->estado          = $request->input('estado') ?? 'Borrador';
            $proforma->vendedor        = $request->input('responsable_nombre');
            $proforma->ruc_cedula         = $request->input('ruc');
            $proforma->telefono           = $request->input('telefono');
            $proforma->direccion_proyecto = $request->input('direccion_proyecto');
            $proforma->condiciones        = $request->input('condiciones');
            $proforma->descuento          = (float) $request->input('descuento_val', 0);

            // --- CORREO ELECTRÓNICO DEL CLIENTE (creación) ---
            // Se protege con Schema::hasColumn para evitar errores si la migración
            // aún no se ha ejecutado (misma estrategia que la columna 'moneda').
            if (Schema::hasColumn('proformas', 'correo')) {
                $proforma->correo = $request->input('correo');
            }

            // --- PERSISTENCIA EXPLÍCITA DE LA MONEDA (creación) ---
            // Se guarda el símbolo elegido en la calculadora ($ / C$) para que la
            // edición posterior y el PDF muestren la moneda correcta.
            if (Schema::hasColumn('proformas', 'moneda')) {
                $proforma->moneda = $request->input('moneda_simbolo', '$');
            }

            $proforma->save();

            // Las filas se normalizan antes de guardarse: se recalcula la POSICIÓN secuencial
            // (1, 2, 3...) y se renumera el título según esa posición. El mismo arreglo
            // normalizado alimenta el PDF inmediato, de modo que el PDF recién generado
            // coincide exactamente con lo persistido y con el PDF re-descargado.
            $items = $this->normalizarItems($request->input('items') ?? []);
            foreach ($items as $item) {
                $detalle = new ProformaDetalle();
                $detalle->proforma_id     = $proforma->id;

                // Posición explícita de la línea dentro de la proforma (evita depender del orden físico)
                if (Schema::hasColumn('proforma_detalles', 'orden')) {
                    $detalle->orden = $item['orden'];
                }

                // El campo 'titulo' es el encabezado del servicio (ej: "1. Primer tramo") y 'desc' es el detalle.
                // Se guardan juntos en 'descripcion': la PRIMERA línea = TÍTULO (se imprime en negrita en el PDF),
                // el resto de líneas = DETALLE (texto normal).
                $detalle->descripcion     = $item['descripcion'];
                $detalle->cantidad        = $item['cant'];
                $detalle->precio_unitario = $item['precio'];
                $detalle->subtotal        = $item['subtotal'];
                $detalle->save();
            }

            DB::commit();
        } catch (Exception $e) {
            DB::rollBack();

            // El detalle técnico queda registrado en el log del servidor
            // (storage/logs/laravel.log) y NUNCA se expone al usuario.
            // Se eliminó dd(): rompía la respuesta HTTP, mataba el proceso de PHP
            // y filtraba rutas internas del servidor en pantalla.
            Log::error('Error al guardar la proforma: ' . $e->getMessage(), [
                'archivo' => $e->getFile(),
                'linea'   => $e->getLine(),
            ]);

            return back()
                ->withInput()
                ->withErrors(['proforma' => 'No se pudo guardar la proforma. Revise los datos e intente nuevamente.']);
        }

        // 3) SÓLO DESPUÉS del commit se incrementa el contador de Firebase (H-04): si el
        //    guardado falla y hay rollback, el folio NO queda quemado. La escritura va
        //    condicionada por ETag y verificada por el servicio (H-01).
        try {
            $this->contadorService->incrementarContador($valorActual);
        } catch (Exception $e) {
            // La proforma YA está guardada, así que la respuesta al usuario no se rompe;
            // se deja registro crítico para reparar el contador manualmente si hiciera falta.
            Log::critical(
                'La proforma ' . $codigoFinal . ' se guardó, pero el contador de Firebase no pudo incrementarse: '
                . $e->getMessage()
            );
        }

        // 4) PRG (H-05): se redirige a la descarga del PDF por id. Un F5 repite el GET,
        //    nunca el POST, de modo que no se duplica la proforma ni se consume otro folio.
        return redirect()->route('proformas.pdf', $proforma->id);
    }

    // =====================================================
    // 4. ACTUALIZAR PROFORMA EXISTENTE
    // =====================================================
    public function update(Request $request, $id)
    {
        // VALIDACIÓN: el correo es opcional (nullable), pero si se envía debe
        // ser una dirección de email válida y de máximo 255 caracteres.
        $request->validate([
            'correo' => ['nullable', 'email', 'max:255'],
        ]);

        DB::beginTransaction();

        try {
            $proforma = Proforma::findOrFail($id);

            // CAPTURA Y ACTUALIZACIÓN DE FECHA
            if ($request->filled('fecha_emision')) {
                $proforma->fecha_emision = $request->input('fecha_emision');
            }

            $proforma->cliente            = $request->input('cliente') ?? $proforma->cliente;
            $proforma->subtotal           = (float) $request->input('subtotal_val', 0);
            $proforma->impuesto           = (float) $request->input('iva_val', 0);
            $proforma->total              = (float) $request->input('total_val', 0);
            $proforma->observaciones      = $request->input('contacto') ?? $proforma->observaciones;
            $proforma->estado             = $request->input('estado') ?? $proforma->estado;
            $proforma->vendedor           = $request->input('responsable_nombre') ?? $proforma->vendedor;
            $proforma->ruc_cedula         = $request->input('ruc') ?? $proforma->ruc_cedula;
            $proforma->telefono           = $request->input('telefono') ?? $proforma->telefono;
            $proforma->direccion_proyecto = $request->input('direccion_proyecto') ?? $proforma->direccion_proyecto;
            $proforma->condiciones        = $request->input('condiciones') ?? $proforma->condiciones;
            $proforma->descuento          = (float) $request->input('descuento_val', 0);

            // --- CORREO ELECTRÓNICO DEL CLIENTE (edición) ---
            // Si el usuario borra el correo (cadena vacía), se guarda vacío;
            // si el campo no viene en la petición, se conserva el valor actual.
            if (Schema::hasColumn('proformas', 'correo')) {
                $proforma->correo = $request->input('correo') ?? $proforma->correo;
            }

            // --- PERSISTENCIA EXPLÍCITA DE LA MONEDA (edición) ---
            // Se guarda el símbolo enviado por el formulario ($ = Dólares / C$ = Córdobas).
            // La moneda elegida por el usuario prevalece: el controlador NO la recalcula
            // a partir del precio por defecto del catálogo de servicios.
            if (Schema::hasColumn('proformas', 'moneda')) {
                $proforma->moneda = $request->input('moneda_simbolo', '$');
            }

            $proforma->save();

            DB::table('proforma_detalles')->where('proforma_id', $proforma->id)->delete();

            // Se normalizan las filas (posición secuencial + renumerado del título) antes de
            // reinsertarlas; además se omite toda fila realmente vacía, igual que en la creación.
            $items = $this->normalizarItems($request->input('items') ?? []);
            foreach ($items as $item) {
                $detalle = new ProformaDetalle();
                $detalle->proforma_id     = $proforma->id;

                // Posición explícita de la línea dentro de la proforma (evita depender del orden físico)
                if (Schema::hasColumn('proforma_detalles', 'orden')) {
                    $detalle->orden = $item['orden'];
                }

                // El campo 'titulo' es el encabezado del servicio (ej: "1. Primer tramo") y 'desc' es el detalle.
                // Se guardan juntos en 'descripcion': la PRIMERA línea = TÍTULO (se imprime en negrita en el PDF),
                // el resto de líneas = DETALLE (texto normal).
                $detalle->descripcion     = $item['descripcion'];

                if (Schema::hasColumn('proforma_detalles', 'text')) {
                    $detalle->text = $item['descripcion'];
                }

                $detalle->cantidad        = $item['cant'];
                $detalle->precio_unitario = $item['precio'];
                $detalle->subtotal        = $item['subtotal'];

                $detalle->save();
            }

            DB::commit();

            // PRG (H-05): la edición ya está comprometida; se redirige a la descarga del
            // PDF para que un F5 repita el GET y nunca el PUT del formulario.
            return redirect()->route('proformas.pdf', $proforma->id);

        } catch (Exception $e) {
            DB::rollBack();

            // Mismo criterio que en generarPDF(): log técnico en el servidor y
            // mensaje amigable al usuario mediante back()->withErrors().
            Log::error('Error al actualizar la proforma: ' . $e->getMessage(), [
                'archivo' => $e->getFile(),
                'linea'   => $e->getLine(),
            ]);

            return back()
                ->withInput()
                ->withErrors(['proforma' => 'No se pudo actualizar la proforma. Revise los datos e intente nuevamente.']);
        }
    }

    // =====================================================
    // 5. GENERAR Y VER PDF POR ID (PETICIÓN GET)
    // =====================================================
    public function generarPDFPorId($id)
    {
        $proforma = Proforma::with('detalles')->findOrFail($id);

        $numContador = '0001';
        if (!empty($proforma->codigo_proforma)) {
            $partes = explode('-', $proforma->codigo_proforma);
            $numContador = end($partes);
        }

        $itemsBD = [];
        foreach ($proforma->detalles as $detalle) {
            $itemsBD[] = [
                'desc' => $detalle->descripcion,
                'cant' => $detalle->cantidad,
                'precio' => $detalle->precio_unitario,
            ];
        }

        $fakeRequest = new Request([
            'cliente'            => $proforma->cliente,
            'contacto'           => $proforma->observaciones,
            'ruc'                => $proforma->ruc_cedula,
            'telefono'           => $proforma->telefono,
            'correo'             => $proforma->correo,
            'direccion_proyecto' => $proforma->direccion_proyecto,
            'items'              => $itemsBD,
            'subtotal_val'       => $proforma->subtotal,
            'descuento_val'      => $proforma->descuento,
            'iva_val'            => $proforma->impuesto,
            'total_val'          => $proforma->total,
            'moneda_simbolo'     => $proforma->moneda ?? '$',
            'responsable_nombre' => $proforma->vendedor,
            'condiciones'        => $proforma->condiciones,
            'fecha_emision'      => $proforma->fecha_emision,
        ]);

        return $this->descargarPdfMapeado($fakeRequest, $numContador, $proforma->fecha_emision);
    }

    // Alias por compatibilidad
    public function pdfPorId($id)
    {
        return $this->generarPDFPorId($id);
    }

    /**
     * Formatea el correlativo tal como se imprime en la proforma (4 dígitos).
     * Se mantiene idéntico al comportamiento histórico (str_pad con '0' a la izquierda).
     */
    private function formatearContador(int $valor): string
    {
        return str_pad((string) $valor, 4, '0', STR_PAD_LEFT);
    }

    // =====================================================
    // 5.b NORMALIZACIÓN DE LAS LÍNEAS DE SERVICIO
    // =====================================================
    /**
     * Convierte las filas crudas del formulario (items[i][titulo], [desc], [cant], [precio])
     * en filas listas para persistir, GARANTIZANDO el orden secuencial:
     *
     *  1. Se descartan las filas totalmente vacías (sin título, sin detalle y sin precio).
     *  2. Se asigna 'orden' = 1, 2, 3... según la posición real de la fila en el formulario.
     *  3. Se renumera el prefijo del título ("3. ", "12. ") según ese 'orden', de modo que el
     *     número impreso coincida con la posición de la línea (fuente única de verdad).
     *     SOLO se reescribe si el título ya traía un prefijo "N."; los títulos sin numerar
     *     se respetan tal como los escribió el usuario.
     *  4. Se reconstruye 'descripcion' con el formato que espera el PDF/vistas:
     *     PRIMERA línea = TÍTULO, líneas siguientes = DETALLE.
     *
     * @param  array  $items
     * @return array<int, array<string, mixed>>
     */
    private function normalizarItems($items): array
    {
        $normalizados = [];
        $orden = 0;

        foreach ($items as $item) {
            $tituloRaw    = trim((string) ($item['titulo'] ?? ''));
            $detalleTexto = trim((string) ($item['desc'] ?? ''));
            $precio       = (float) ($item['precio'] ?? 0);
            $cantidad     = (int) ($item['cant'] ?? 1);

            // 1. Omitir filas totalmente vacías (mismo criterio en creación y edición)
            if ($tituloRaw === '' && $detalleTexto === '' && $precio == 0.0) {
                continue;
            }

            // 2. Posición secuencial real
            $orden++;

            // 3. Renumerado del título: SOLO se reescribe si el título ya trae un prefijo
            //    de línea "N." (por ejemplo "3. Jardin trasero" -> "2. Jardin trasero").
            //    Los títulos sin numerar se respetan tal cual (evita mutilar textos como
            //    "29 aspersores industriales." o "2 Temporizadores de doble salida").
            $tituloItem = $tituloRaw;

            if (preg_match('/^\s*\d+\.\s*/u', $tituloRaw)) {
                $tituloBase = trim((string) preg_replace('/^\s*\d+\.\s*/u', '', $tituloRaw));

                // Si sólo había el número (ej: "2."), se conserva como numeración de la línea
                $tituloItem = $tituloBase !== '' ? $orden . '. ' . $tituloBase : $orden . '.';
            }

            // 4. Descripción final: 1ª línea = título, resto = detalle
            $descripcion = ($tituloItem !== '' && $detalleTexto !== '')
                ? $tituloItem . "\n" . $detalleTexto
                : ($tituloItem !== '' ? $tituloItem : $detalleTexto);

            $normalizados[] = [
                'orden'       => $orden,
                'titulo'      => $tituloItem,
                'desc'        => $descripcion !== '' ? $descripcion : 'Servicio Profesional',
                'descripcion' => $descripcion !== '' ? $descripcion : 'Servicio Profesional',
                'cant'        => $cantidad,
                'precio'      => $precio,
                'subtotal'    => $cantidad * $precio,
            ];
        }

        return $normalizados;
    }

    // =====================================================
    // 6. FUNCIÓN INTERNA PRIVADA PARA RENDERIZAR EL PDF
    // =====================================================
    private function descargarPdfMapeado(Request $request, $nuevoContador, $fechaEmision = null)
    {
        $logo = null;
        $posiblesLogos = [
            public_path('imagen/LOGO JPG.jpg'),
            public_path('imagen/logo.jpg'),
            public_path('imagen/logo.png')
        ];

        foreach ($posiblesLogos as $path) {
            if (file_exists($path) && is_readable($path)) {
                try {
                    $extension = pathinfo($path, PATHINFO_EXTENSION);
                    $logoData = base64_encode(file_get_contents($path));
                    $logo = 'data:image/' . $extension . ';base64,' . $logoData;
                    break;
                } catch (Exception $ex) {
                    $logo = null;
                }
            }
        }

        $responsableNombre = $request->input('responsable_nombre') ?? $request->input('pdf_firma_nombre');
        $firmas = [
            'Jammy Silva'     => ['cargo' => 'Arquitecta Coordinadora', 'tel' => '8588-5337'],
            'Maura Benavides' => ['cargo' => 'Ejecutiva de Negocios', 'tel' => '8560-0648'],
            'Stephany Mejia'  => ['cargo' => 'Gerente Comercial', 'tel' => '8998-0892'],
            'Josep Hernandez' => ['cargo' => 'Arquitecto Supervisor', 'tel' => '8373-2510'],
            'Braulio Duarte'  => ['cargo' => 'Jefe de Ventas-Empresariales', 'tel' => '7886-2971'],
            'Jan Herrera'     => ['cargo' => ' Ventas-Empresariales', 'tel' => '8380-8039']
        ];

        if (array_key_exists($responsableNombre, $firmas)) {
            $cargo = $firmas[$responsableNombre]['cargo'];
            $tel   = $firmas[$responsableNombre]['tel'];
        } else {
            $cargo = $request->input('pdf_firma_cargo', 'Coordinador');
            $tel   = $request->input('pdf_firma_tel', '0000-0000');
        }

        $fechaRaw = $fechaEmision ?? $request->input('fecha_emision') ?? date('Y-m-d');
        $fechaFormateada = date('d/m/Y', strtotime($fechaRaw));

        $data = [
            'logo'               => $logo,
            'nuevoContador'      => $nuevoContador,
            'cliente'            => $request->input('cliente'),
            'contacto'           => $request->input('contacto'),
            'ruc'                => $request->input('ruc'),
            'telefono'           => $request->input('telefono'),
            'correo'             => $request->input('correo'),
            'direccion_proyecto' => $request->input('direccion_proyecto'),
            'items'              => $request->input('items') ?? [],
            'subtotal'           => (float) $request->input('subtotal_val', 0),
            'descuento'          => (float) $request->input('descuento_val', 0),
            'iva'                => (float) $request->input('iva_val', 0),
            'total'              => (float) $request->input('total_val', 0),
            'moneda_simbolo'     => $request->input('moneda_simbolo') ?? '$',
            'responsable_nombre' => $responsableNombre,
            'responsable_cargo'  => $cargo,
            'responsable_tel'    => $tel,
            'fecha'              => $fechaFormateada,
            'condiciones'        => $request->input('condiciones'),
        ];

        $pdf = Pdf::loadView('factura', $data)
                  ->setPaper('letter', 'portrait')
                  ->setOptions([
                      'isRemoteEnabled' => true,
                      'isHtml5ParserEnabled' => true
                  ]);

        $nombreArchivo = 'Prof-ready-' . $nuevoContador . '.pdf';

        return $pdf->stream($nombreArchivo);
    }
}
