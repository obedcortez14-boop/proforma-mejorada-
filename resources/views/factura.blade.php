<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Proforma Ready</title>
    {{-- Montserrat (Google Fonts): pesos 500 Medium, 700 Bold y 900 Black --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@500;700;900&display=swap" rel="stylesheet">
    <style>
        @page {
            size: letter;
            margin: 8mm 10mm;
        }

        {{-- Montserrat incrustada en TTF (500/700/900): DomPDF solo acepta fuentes
             con format('truetype') (descarta WOFF2), por lo que además del import de
             Google Fonts se registran los TTF locales vía data-URI para garantizar
             la jerarquía tipográfica en el PDF. --}}
        @if (file_exists(public_path('fonts/Montserrat-Medium.ttf')))
        @font-face {
            font-family: 'Montserrat';
            font-style: normal;
            font-weight: 500;
            src: url('data:font/truetype;base64,{{ base64_encode(file_get_contents(public_path('fonts/Montserrat-Medium.ttf'))) }}') format('truetype');
        }
        @endif
        @if (file_exists(public_path('fonts/Montserrat-Bold.ttf')))
        @font-face {
            font-family: 'Montserrat';
            font-style: normal;
            font-weight: 700;
            src: url('data:font/truetype;base64,{{ base64_encode(file_get_contents(public_path('fonts/Montserrat-Bold.ttf'))) }}') format('truetype');
        }
        @endif
        @if (file_exists(public_path('fonts/Montserrat-Black.ttf')))
        @font-face {
            font-family: 'Montserrat';
            font-style: normal;
            font-weight: 900;
            src: url('data:font/truetype;base64,{{ base64_encode(file_get_contents(public_path('fonts/Montserrat-Black.ttf'))) }}') format('truetype');
        }
        @endif

        body {
            font-family: 'Montserrat', 'Helvetica', Arial, sans-serif;
            font-weight: 500;
            color: #2d3748;
            margin: 0;
            padding: 0;
            background-color: white;
            font-size: 9.5px;
            line-height: 1.25;
        }

        /* Encabezado compacto */
        .header {
            background-color: #3d5229;
            color: white;
            padding: 10px 15px;
            border-radius: 6px;
        }
        .header-table {
            width: 100%;
            border-collapse: collapse;
        }
        .header-table td {
            vertical-align: middle;
        }

        /* Contenedor del Logo */
        .logo-box {
            background: white;
            padding: 4px;
            border-radius: 4px;
            width: 75px;
            text-align: center;
        }
        .logo-box img {
            width: 100%;
            height: auto;
            display: block;
        }

        /* Título Central */
        .title-td {
            text-align: center;
        }
        .proforma-title {
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            font-weight: bold;
            border-top: 1px solid rgba(255,255,255,0.3);
            border-bottom: 1px solid rgba(255,255,255,0.3);
            padding: 4px 10px;
            display: inline-block;
        }

        /* Recuadro de Información */
        .info-box {
            background: white;
            color: #333;
            padding: 5px;
            border-radius: 4px;
            width: 100px;
            margin-left: auto;
            text-align: center;
        }
        .info-box .no-label { font-size: 7.5px; font-weight: bold; color: #666; letter-spacing: 0.5px; }
        .info-box .no-number { font-size: 13px; font-weight: bold; color: #e63946; margin: 1px 0; }
        .info-box .date-line { border-top: 1px solid #eee; padding-top: 2px; font-size: 7.5px; color: #888; font-weight: bold; }

        .red-bar {
            background-color: #e63946;
            height: 3px;
            width: 25%;
            margin: -1.5px auto 0;
            border-radius: 4px;
        }

        /* Información del Cliente */
        .client-section { padding: 10px 5px 5px 5px; }
        .grid { width: 100%; border-collapse: collapse; }
        .col { width: 50%; vertical-align: top; }
        .label { font-size: 7.5px; font-weight: bold; color: #3d5229; text-transform: uppercase; margin-bottom: 1px; }
        .value { border-bottom: 1px solid #edf2f7; padding: 2px 0; margin-bottom: 6px; font-size: 9px; color: #2d3748; }

        /* Tabla de Items */
        .items-table {
            width: 100%;
            margin: 8px 0;
            border-collapse: collapse;
            table-layout: fixed;
        }
        .items-table th { background-color: #3d5229; color: white; font-size: 8.5px; text-transform: uppercase; padding: 5px; text-align: center; }
        .items-table td { border: 1px solid #e2e8f0; padding: 4px; font-size: 9px; }
        .items-table td.col-descripcion { word-wrap: break-word; white-space: pre-line; vertical-align: top; text-align: left; font-family: 'Montserrat', sans-serif; font-weight: 500; font-size: 10px; color: #374151; }
        /* Formato de descripciones complejas (formatDescripcionProforma) */
        /* Jerarquía tipográfica Montserrat (descripción de servicios) */
        .desc-titulo { font-family: 'Montserrat', sans-serif; font-weight: 900; text-transform: uppercase; font-size: 13px; color: #111827; display: block; margin-bottom: 2px; }
        .desc-clave { font-family: 'Montserrat', sans-serif; font-weight: 700; text-transform: uppercase; font-size: 11px; color: #1f2937; }

        /* Estructura de Cierre Balanceada (Lado a Lado) */
        .summary-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 5px;
        }
        .summary-table td { vertical-align: top; }

        /* Cuadro de Condiciones */
        .validity-box {
            border: 1px dashed #3d5229;
            background-color: #fcfdfe;
            padding: 6px 8px;
            border-radius: 6px;
            width: 92%;
        }
        .validity-title {
            color: #3d5229;
            font-weight: bold;
            text-transform: uppercase;
            font-size: 8px;
            margin-bottom: 3px;
        }
        .editable-conditions-block {
            width: 100%;
            font-size: 8px;
            color: #475569;
            line-height: 1.3;
            outline: none;
        }

        /* Totales */
        .totals-table { width: 100%; border-collapse: collapse; }
        .totals-table td { padding: 4px 6px; border: 1px solid #edf2f7; font-size: 9px; }
        .total-row { background-color: #3d5229; color: white; font-weight: bold; }
        .text-right { text-align: right; }

        /* Pie de página y Firmas */
        .footer-section {
            margin-top: 20px;
            width: 100%;
            border-collapse: collapse;
        }
        .footer-section td { vertical-align: bottom; text-align: center; }
        .signature-container { width: 160px; margin: 0 auto; }
        .signature-line { border-top: 1px solid #3d5229; margin-bottom: 3px; }
        .responsable-name { color: #3d5229; font-weight: bold; text-transform: uppercase; font-size: 10px; }
        .responsable-cargo { font-size: 8px; color: #718096; text-transform: uppercase; }

        .company-address {
            margin-top: 15px;
            padding-top: 8px;
            border-top: 1px solid #e2e8f0;
            text-align: center;
            font-size: 8px;
            color: #718096;
        }

        @media print {
            body { background: white; color: #000; }
            .header { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .validity-box { border: 1px dashed #3d5229 !important; background-color: #fcfdfe !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .total-row { background-color: #3d5229 !important; color: white !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .items-table th { background-color: #3d5229 !important; color: white !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        }
    </style>
</head>
<body>

    <div class="header">
        <table class="header-table">
            <tr>
                <td style="width: 25%;">
                   <div class="logo-box">
    @if(!empty($logo))
        <img src="{{ $logo }}" alt="Logo" style="max-width: 150px; height: auto;">
    @elseif(file_exists(public_path('imagen/LOGO JPG.jpg')))
        <img src="data:image/jpeg;base64,{{ base64_encode(file_get_contents(public_path('imagen/LOGO JPG.jpg'))) }}" alt="Logo">
    @elseif(file_exists(public_path('logo.jpg')))
        <img src="data:image/jpeg;base64,{{ base64_encode(file_get_contents(public_path('logo.jpg'))) }}" alt="Logo">
    @else
        <h4 style="color: #3d5229; margin: 3px 0; font-weight: bold; font-size: 10px;">READY</h4>
    @endif
</div>
                </td>
                <td class="title-td">
                    <div class="proforma-title">Proforma de Servicios</div>
                </td>
                <td style="width: 25%;">
                    <div class="info-box">
                        <div class="no-label">PROFORMA NO.</div>
                        <div class="no-number">{{ $nuevoContador }}</div>
                        <div class="date-line">FECHA: {{ $fecha }}</div>
                    </div>
                </td>
            </tr>
        </table>
    </div>

    <div class="red-bar"></div>

    <div class="client-section">
        <table class="grid">
            <tr>
                <td class="col">
                    <div class="label">Cliente</div>
                    <div class="value">{{ $cliente ?? 'N/A' }}</div>
                    <div class="label">RUC / Cédula</div>
                    <div class="value">{{ $ruc ?? 'N/A' }}</div>
                </td>
                <td class="col" style="padding-left: 20px;">
                    <div class="label">Contacto</div>
                    <div class="value">{{ $contacto ?? 'N/A' }}</div>
                    <div class="label">Teléfono</div>
                    <div class="value">{{ $telefono ?? 'N/A' }}</div>
                </td>
            </tr>
            <tr>
                <td colspan="2">
                    <div class="label">Correo</div>
                    <div class="value">{{ $correo ?? 'N/A' }}</div>
                </td>
            </tr>
            <tr>
                <td colspan="2">
                    <div class="label">Dirección del Proyecto</div>
                    <div class="value" style="margin-bottom: 2px;">{{ $direccion_proyecto ?? $direccion ?? 'N/A' }}</div>
                </td>
            </tr>
        </table>
    </div>

    <table class="items-table">
        <thead>
            <tr>
                <th style="width: 55%; text-align: left;">Descripción Detallada</th>
                <th style="width: 10%;">Cant.</th>
                <th style="width: 17%;">P. Unitario</th>
                <th style="width: 18%;">Subtotal</th>
            </tr>
        </thead>
        <tbody>
            @foreach($items as $item)
            <tr>
                <td class="col-descripcion">{!! formatDescripcionProforma($item['desc'] ?? '') !!}</td>
                <td style="text-align: center; font-weight: bold; vertical-align: top;">{{ $item['cant'] }}</td>
                <td style="text-align: right; vertical-align: top;">{{ $moneda_simbolo ?? '$' }} {{ number_format($item['precio'], 2) }}</td>
                <td style="text-align: right; font-weight: bold; vertical-align: top;">{{ $moneda_simbolo ?? '$' }} {{ number_format($item['cant'] * $item['precio'], 2) }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <table class="summary-table">
        <tr>
            <td style="width: 55%;">
                <div class="validity-box">
                    <div class="validity-title">Condiciones:</div>
                    <div class="editable-conditions-block" id="txtCondiciones">@if(!empty($condiciones) && !is_array($condiciones)){!! nl2br(e($condiciones)) !!}@else• Vigencia de la cotización: 30 días calendario.<br>• Se requiere el 50% de anticipo para iniciar el proyecto.<br>• El 50% restante se cancelará contra entrega del trabajo.<br>• Tiempo de entrega estimado: 3 a 5 días hábiles.<br>• Precios sujetos a cambios sin previo aviso.@endif</div>
                </div>
            </td>

            <td style="width: 45%;">
                <table class="totals-table">
                    <tr>
                        <td style="background: #fcfcfc; color: #555; font-weight: bold;">Subtotal</td>
                        <td class="text-right">{{ $moneda_simbolo ?? '$' }} {{ number_format($subtotal, 2) }}</td>
                    </tr>
                    @if(isset($descuento) && $descuento > 0)
                    <tr>
                        <td style="color: #e63946; font-weight: bold;">Descuento</td>
                        <td class="text-right" style="color: #e63946;">- {{ $moneda_simbolo ?? '$' }} {{ number_format($descuento, 2) }}</td>
                    </tr>
                    @endif
                    @if(isset($iva) && $iva > 0)
                    <tr>
                        <td style="color: #555;">IVA (15%)</td>
                        <td class="text-right">{{ $moneda_simbolo ?? '$' }} {{ number_format($iva, 2) }}</td>
                    </tr>
                    @endif
                    <tr class="total-row">
                        <td style="letter-spacing: 0.5px; font-size: 9px;">TOTAL A PAGAR</td>
                        <td class="text-right" style="font-size: 11px;">{{ $moneda_simbolo ?? '$' }} {{ number_format($total, 2) }}</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <table class="footer-section">
        <tr>
            <td>
                <div class="signature-container">
                    <div class="signature-line"></div>
                    <div class="responsable-name">{{ $responsable_nombre ?? 'Jammy Silva' }}</div>
                    <div class="responsable-cargo">{{ $responsable_cargo ?? 'Supervisora - Coordinadora' }}</div>
                    <div style="font-size: 9px; font-weight: bold; margin-top: 2px; color: #333;">
                        TEL: {{ $responsable_tel ?? '8588-5337' }}
                    </div>
                </div>
            </td>
        </tr>
    </table>

    <div class="company-address">
        <strong>Dirección Oficina Central:</strong> Camino viejo a Santo Domingo. Del AMPM 30m al sur, 300m al oeste. Managua, Nicaragua.
    </div>

</body>
</html>
