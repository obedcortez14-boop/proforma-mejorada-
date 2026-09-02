<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Models\Proforma;
use App\Models\ProformaDetalle;
use Exception;

class CalculadoraController extends Controller
{
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

        DB::beginTransaction();

        try {
            $rutaFirebase = "https://proforma-ready-default-rtdb.firebaseio.com/contador_pdf.json";

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $rutaFirebase);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            $response = curl_exec($ch);
            curl_close($ch);

            if ($response === false) {
                throw new Exception("No se pudo conectar con el servidor de numeración (Firebase).");
            }

            $contadorFirebase = json_decode($response, true);
            $valorActual = is_array($contadorFirebase) ? ($contadorFirebase['contador_pdf'] ?? 1) : ($contadorFirebase ?? 1);

            $nuevoContador = str_pad((string)$valorActual, 4, "0", STR_PAD_LEFT);
            $codigoFinal = 'PROF-' . date('Y') . '-' . $nuevoContador;

            $siguienteValor = $valorActual + 1;
            $updateData = json_encode(['contador_pdf' => $siguienteValor]);

            $chUpdate = curl_init();
            curl_setopt($chUpdate, CURLOPT_URL, $rutaFirebase);
            curl_setopt($chUpdate, CURLOPT_CUSTOMREQUEST, "PATCH");
            curl_setopt($chUpdate, CURLOPT_POSTFIELDS, $updateData);
            curl_setopt($chUpdate, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($chUpdate, CURLOPT_SSL_VERIFYPEER, false);
            curl_exec($chUpdate);
            curl_close($chUpdate);

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

            $items = $request->input('items') ?? [];
            foreach ($items as $item) {
                // El campo 'titulo' es el encabezado del servicio (ej: "1. Primer tramo") y 'desc' es el detalle.
                // Se guardan juntos en 'descripcion': la PRIMERA línea = TÍTULO (se imprime en negrita en el PDF),
                // el resto de líneas = DETALLE (texto normal).
                $tituloItem   = trim((string) ($item['titulo'] ?? ''));
                $detalleTexto = trim((string) ($item['desc'] ?? ''));

                $descripcionCompleta = ($tituloItem !== '' && $detalleTexto !== '')
                    ? $tituloItem . "\n" . $detalleTexto
                    : ($tituloItem !== '' ? $tituloItem : $detalleTexto);

                $detalle = new ProformaDetalle();
                $detalle->proforma_id     = $proforma->id;
                $detalle->descripcion     = $descripcionCompleta !== '' ? $descripcionCompleta : 'Servicio Profesional';
                $detalle->cantidad        = (int) ($item['cant'] ?? 1);
                $detalle->precio_unitario = (float) ($item['precio'] ?? 0);
                $detalle->subtotal        = $detalle->cantidad * $detalle->precio_unitario;
                $detalle->save();
            }

            DB::commit();

            return $this->descargarPdfMapeado($request, $nuevoContador, $proforma->fecha_emision);

        } catch (Exception $e) {
            DB::rollBack();
            dd("Error detectado en el proceso de guardado:", $e->getMessage(), "Archivo: " . $e->getFile() . " Línea: " . $e->getLine());
        }
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

            $items = $request->input('items') ?? [];
            foreach ($items as $item) {
                // El campo 'titulo' es el encabezado del servicio (ej: "1. Primer tramo") y 'desc' es el detalle.
                // Se guardan juntos en 'descripcion': la PRIMERA línea = TÍTULO (se imprime en negrita en el PDF),
                // el resto de líneas = DETALLE (texto normal).
                $tituloItem   = trim((string) ($item['titulo'] ?? ''));
                $detalleTexto = trim((string) ($item['desc'] ?? ''));

                // Omitir filas totalmente vacías (sin título, sin detalle y sin precio)
                if ($tituloItem === '' && $detalleTexto === '' && (float) ($item['precio'] ?? 0) == 0.0) {
                    continue;
                }

                $descripcionCompleta = ($tituloItem !== '' && $detalleTexto !== '')
                    ? $tituloItem . "\n" . $detalleTexto
                    : ($tituloItem !== '' ? $tituloItem : $detalleTexto);

                $textoFinal = $descripcionCompleta !== '' ? $descripcionCompleta : 'Servicio Profesional';

                $detalle = new ProformaDetalle();
                $detalle->proforma_id     = $proforma->id;
                $detalle->descripcion     = $textoFinal;

                if (Schema::hasColumn('proforma_detalles', 'text')) {
                    $detalle->text = $textoFinal;
                }

                $detalle->cantidad        = (int) ($item['cant'] ?? 1);
                $detalle->precio_unitario = (float) ($item['precio'] ?? 0);
                $detalle->subtotal        = $detalle->cantidad * $detalle->precio_unitario;

                $detalle->save();
            }

            DB::commit();

            $numContador = '0001';
            if (!empty($proforma->codigo_proforma)) {
                $partes = explode('-', $proforma->codigo_proforma);
                $numContador = end($partes);
            }

            $request->merge([
                'descuento_val' => (float) $request->input('descuento_val', 0),
                'ruc' => $request->input('ruc') ?? $proforma->ruc_cedula,
                'telefono' => $request->input('telefono') ?? $proforma->telefono,
                'correo' => $request->input('correo') ?? $proforma->correo,
                'direccion_proyecto' => $request->input('direccion_proyecto') ?? $proforma->direccion_proyecto,
                'responsable_nombre' => $request->input('responsable_nombre') ?? $proforma->vendedor,
                'estado' => $request->input('estado') ?? $proforma->estado
            ]);

            return $this->descargarPdfMapeado($request, $numContador, $proforma->fecha_emision);

        } catch (Exception $e) {
            DB::rollBack();
            dd("Error detectado en el proceso de actualización:", $e->getMessage(), "Línea: " . $e->getLine());
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
