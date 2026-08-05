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
            $proforma->fecha_emision   = $fechaEmisionInput; // <-- AHORA USA LA FECHA ENVIADA
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

            $proforma->save();

            $items = $request->input('items') ?? [];
            foreach ($items as $item) {
                $detalle = new ProformaDetalle();
                $detalle->proforma_id     = $proforma->id;
                $detalle->descripcion     = $item['desc'] ?? 'Servicio Profesional';
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

            $proforma->save();

            DB::table('proforma_detalles')->where('proforma_id', $proforma->id)->delete();

            $items = $request->input('items') ?? [];
            foreach ($items as $item) {
                if (empty($item['desc']) && empty($item['precio'])) {
                    continue;
                }

                $detalle = new ProformaDetalle();
                $detalle->proforma_id     = $proforma->id;
                $detalle->descripcion     = $item['desc'] ?? 'Servicio Profesional';

                if (Schema::hasColumn('proforma_detalles', 'text')) {
                    $detalle->text = $item['desc'] ?? 'Servicio Profesional';
                }

                $detalle->text            = $item['desc'] ?? 'Servicio Profesional';
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
    // 5. FUNCIÓN INTERNA PRIVADA PARA RENDERIZAR EL PDF
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
            'Braulio Duarte' => ['cargo' => 'Jefe de Ventas-Empresariales', 'tel' => '7886-2971'],
            'Jan Herrera' => ['cargo' => ' Ventas-Empresariales', 'tel' => '8380 8039']
        ];

        if (array_key_exists($responsableNombre, $firmas)) {
            $cargo = $firmas[$responsableNombre]['cargo'];
            $tel   = $firmas[$responsableNombre]['tel'];
        } else {
            $cargo = $request->input('pdf_firma_cargo', 'Coordinador');
            $tel   = $request->input('pdf_firma_tel', '0000-0000');
        }

        // TOMA LA FECHA PASADA O LA DEL REQUEST O LA ACTUAL COMO ÚLTIMA OPCIÓN
        $fechaRaw = $fechaEmision ?? $request->input('fecha_emision') ?? date('Y-m-d');
        $fechaFormateada = date('d/m/Y', strtotime($fechaRaw));

        $data = [
            'logo'               => $logo,
            'nuevoContador'      => $nuevoContador,
            'cliente'            => $request->input('cliente'),
            'contacto'           => $request->input('contacto'),
            'ruc'                => $request->input('ruc'),
            'telefono'           => $request->input('telefono'),
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