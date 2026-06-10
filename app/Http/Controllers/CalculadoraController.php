<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;

class CalculadoraController extends Controller
{
    public function generarPDF(Request $request)
    {
        // =====================================================
        // 1. LÓGICA DE FIREBASE (LECTURA Y INCREMENTO)
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
        // 2. MANEJO DEL LOGO CORPORATIVO
        // =====================================================
        $logo = null;
        $logoPath = public_path('imagen/LOGO JPG.jpg');
        if (file_exists($logoPath)) {
            $extension = pathinfo($logoPath, PATHINFO_EXTENSION);
            $logoData = base64_encode(file_get_contents($logoPath));
            $logo = 'data:image/' . $extension . ';base64,' . $logoData;
        }

        // =====================================================
        // 3. MAPEO DE RESPONSABLES Y ASIGNACIÓN DE CARGOS
        // =====================================================
        $responsableNombre = $request->input('responsable_nombre') ?? $request->input('pdf_firma_nombre');

        $firmas = [
            'Jammy Silva'      => ['cargo' => 'Arquitecta Coordinadora', 'tel' => '8588-5337'],
            'Maura Benavides'  => ['cargo' => 'Ejecutiva de Negocios', 'tel' => '8560-0648'],
            'Stefany Mejia'    => ['cargo' => 'Jefa de Ventas', 'tel' => '8998-0892'],
            'Henrry Gutierrez' => ['cargo' => 'Representante de Ventas', 'tel' => '82529465'],
        ];

        if (array_key_exists($responsableNombre, $firmas)) {
            $cargo = $firmas[$responsableNombre]['cargo'];
            $tel   = $firmas[$responsableNombre]['tel'];
        } else {
            $cargo = $request->input('pdf_firma_cargo', 'Coordinador');
            $tel   = $request->input('pdf_firma_tel', '0000-0000');
        }

        // =====================================================
        // 4. PREPARACIÓN DEL ARRAY DE DATOS FINALES
        // =====================================================
        $data = [
            'logo'               => $logo,
            'nuevoContador'      => $nuevoContador,
            'cliente'            => $request->input('cliente'),
            'contacto'           => $request->input('contacto'),
            'ruc'                => $request->input('ruc'),
            'telefono'           => $request->input('telefono'),
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
            'fecha'              => Carbon::now('America/Managua')->format('d/m/Y'),

            // Texto plano dinámico editable recibido desde el frontend
            'condiciones'        => $request->input('condiciones') ?? "• Vigencia de la cotización: 30 días calendario.\n• Se requiere el 50% de anticipo para iniciar el proyecto.\n• El 50% restante se cancelará contra entrega del trabajo.\n• Tiempo de entrega estimado: 5 a 7 días hábiles.\n• Precios sujetos a cambios sin previo aviso.",
        ];

        // =====================================================
        // 5. RENDERIZADO Y DESCARGA DEL ARCHIVO PDF
        // =====================================================
        $pdf = Pdf::loadView('factura', $data);
        $pdf->setPaper('letter', 'portrait'); // Cambiado a 'letter' (Tamaño Carta) para coincidir con tus estilos @page

        $nombreArchivo = 'Proforma_Ready_' . ($request->input('cliente') ?? 'SinNombre') . '.pdf';

        return $pdf->download($nombreArchivo);
    }
}
