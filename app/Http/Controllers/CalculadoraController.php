<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Barryvdh\DomPDF\Facade\Pdf;

class CalculadoraController extends Controller
{
    public function generarPDF(Request $request)
    {
        // =====================================================
        // 1. PEGA AQUÍ LA LÓGICA DE FIREBASE (LECTURA Y SUMA)
        // =====================================================
        $rutaFirebase = "https://proforma-ready-default-rtdb.firebaseio.com/contador_pdf.json";

        // --- LECTURA ---
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $rutaFirebase);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $response = curl_exec($ch);
        curl_close($ch);

        $contadorFirebase = json_decode($response, true);
        $valorActual = is_array($contadorFirebase) ? ($contadorFirebase['contador_pdf'] ?? 1) : ($contadorFirebase ?? 1);

        // Formateamos para el PDF actual (Ejemplo: 0001)
        $nuevoContador = str_pad((string)$valorActual, 4, "0", STR_PAD_LEFT);

        // --- INCREMENTAR (+1) PARA LA PRÓXIMA VEZ ---
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
        // =====================================================


        // 2. Manejo del Logo (Esto ya lo tenías)
        $logo = null;
        $logoPath = public_path('imagen/LOGO JPG.jpg');
        if (file_exists($logoPath)) {
            $extension = pathinfo($logoPath, PATHINFO_EXTENSION);
            $logoData = base64_encode(file_get_contents($logoPath));
            $logo = 'data:image/' . $extension . ';base64,' . $logoData;
        }

        // 3. Mapeo de responsables (Esto ya lo tenías)
        $responsableNombre = $request->input('responsable_nombre') ?? $request->input('pdf_firma_nombre');
        $firmas = [
            'Jammy Silva'     => ['cargo' => 'Arquitecta Coordinadora', 'tel' => '8588-5337'],
            'Maura Benavides'  => ['cargo' => 'Ejecutiva de Negocios', 'tel' => '8560-0648'],
            'Stefany Mejia'   => ['cargo' => 'Jefa de Ventas', 'tel' => '8998-0892'],
            'Henrry Gutierrez' => ['cargo' => 'Representante de Ventas', 'tel' => '82529465'],
             'Maura Benavides'  => ['cargo' => 'Ejecutiva de Negocios', 'tel' => '8560-0648'],
        ];

        if (array_key_exists($responsableNombre, $firmas)) {
            $cargo = $firmas[$responsableNombre]['cargo'];
            $tel = $firmas[$responsableNombre]['tel'];
        } else {
            $cargo = $request->input('pdf_firma_cargo', 'Coordinador');
            $tel = $request->input('pdf_firma_tel', '0000-0000');
        }

        // 4. Preparación del array de datos (IMPORTANTE: incluir nuevoContador)
        $data = [
            'logo'               => $logo,
            'nuevoContador'      => $nuevoContador, // <--- Aquí pasamos el número a la factura
            'cliente'            => $request->input('cliente'),
            'contacto'           => $request->input('contacto'),
            'ruc'                => $request->input('ruc'),
            'telefono'           => $request->input('telefono'),
            // CORRECCIÓN: Captura el nombre exacto que pusimos en el formulario HTML
            'direccion_proyecto' => $request->input('direccion_proyecto') ?? $request->input('direccion'),
            'items'              => $request->input('items') ?? [],
            'subtotal'           => (float) $request->input('subtotal_val', 0),
            'descuento'          => (float) $request->input('descuento_val', 0),
            'iva'                => (float) $request->input('iva_val', 0),
            'total'              => (float) $request->input('total_val', 0),
            'moneda_simbolo'     => $request->input('moneda_simbolo') ?? '$',
            'responsable_nombre' => $responsableNombre,
            'responsable_cargo'  => $cargo,
            'responsable_tel'    => $tel,
            'fecha'              => date('d/m/Y'),
        ];

        $pdf = Pdf::loadView('factura', $data);
        $pdf->setPaper('A4', 'portrait');

        $nombreArchivo = 'Proforma_Ready_' . ($request->input('cliente') ?? 'SinNombre') . '.pdf';
        return $pdf->download($nombreArchivo);
    }
}
