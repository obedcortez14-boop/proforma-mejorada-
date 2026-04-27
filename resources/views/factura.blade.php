<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Proforma Ready</title>
    <style>
        @page { margin: 0; }
        body {
            font-family: 'Helvetica', sans-serif;
            color: #333;
            margin: 0;
            padding: 0;
            background-color: white;
        }

        /* Encabezado: Tabla flexible para evitar cortes de texto */
        .header {
            background-color: #3d5229;
            color: white;
            padding: 40px 50px 50px 50px;
            position: relative;
        }

        .header-table {
            width: 100%;
            border-collapse: collapse;
            /* Quitamos table-layout: fixed para que el centro pueda expandirse */
        }

        .header-table td {
            vertical-align: middle;
        }

        /* Contenedor del Logo (Izquierda) */
        .logo-box {
            background: white;
            padding: 10px;
            border-radius: 12px;
            width: 130px; /* Tamaño controlado */
            box-shadow: 0 4px 8px rgba(0,0,0,0.15);
            text-align: center;
        }
        .logo-box img {
            width: 100%;
            height: auto;
            display: block;
        }

        /* Título Central - Ajustado para que no se solape */
        .title-td {
            text-align: center;
            padding: 0 20px;
        }
        .proforma-title {
            font-size: 24px; /* Un poco más grande para destacar */
            text-transform: uppercase;
            letter-spacing: 4px;
            font-weight: bold;
            border-top: 1px solid rgba(255,255,255,0.4);
            border-bottom: 1px solid rgba(255,255,255,0.4);
            padding: 15px 10px;
            display: inline-block;
            line-height: 1.2;
        }

        /* Recuadro de Información (Derecha) */
        .info-box {
            background: white;
            color: #333;
            padding: 12px;
            border-radius: 12px;
            width: 150px;
            margin-left: auto; /* Empuja a la derecha */
            box-shadow: 0 4px 8px rgba(0,0,0,0.15);
            text-align: center;
        }
        .info-box .no-label { font-size: 9px; font-weight: bold; color: #666; letter-spacing: 1px; }
        .info-box .no-number { font-size: 22px; font-weight: bold; color: #e63946; margin: 2px 0; }
        .info-box .date-line { border-top: 1px solid #eee; padding-top: 6px; font-size: 9px; color: #888; font-weight: bold; }

        /* Barra Roja Decorativa */
        .red-bar {
            background-color: #e63946;
            height: 8px;
            width: 30%;
            margin: -4px auto 0;
            position: relative;
            z-index: 10;
            border-radius: 10px;
        }

        /* Información del Cliente */
        .client-section { padding: 40px 50px 20px 50px; }
        .grid { width: 100%; border-collapse: collapse; }
        .col { width: 50%; vertical-align: top; }
        .label { font-size: 9px; font-weight: bold; color: #3d5229; text-transform: uppercase; margin-bottom: 4px; }
        .value { border-bottom: 1px solid #edf2f7; padding: 6px 0; margin-bottom: 20px; font-size: 12px; color: #2d3748; }

        /* Tabla de Items */
        .items-table { width: calc(100% - 100px); margin: 0 50px; border-collapse: collapse; }
        .items-table th { background-color: #3d5229; color: white; font-size: 10px; text-transform: uppercase; padding: 12px; text-align: center; }
        .items-table td { border: 1px solid #f1f5f9; padding: 10px; font-size: 11px; }

        /* Totales */
        .totals-table { margin-top: 25px; margin-left: auto; width: 35%; margin-right: 50px; border-collapse: collapse; }
        .totals-table td { padding: 10px; border: 1px solid #f1f5f9; font-size: 12px; }
        .total-row { background-color: #3d5229; color: white; font-weight: bold; }
        .text-right { text-align: right; }

        /* Pie de página */
        .footer { text-align: center; margin-top: 60px; padding-bottom: 40px; }
        .signature-line { width: 220px; border-top: 2px solid #3d5229; margin: 0 auto 12px; }
        .responsable-name { color: #3d5229; font-weight: bold; text-transform: uppercase; font-size: 14px; }
    </style>
</head>
<body>
    <div class="header">
        <table class="header-table">
            <tr>
                <td style="width: 20%;">
                    <div class="logo-box">
                        @if(isset($logo) && $logo)
                            <img src="{{ $logo }}" alt="Logo">
                        @else
                            <h3 style="color: #3d5229; margin: 20px 0;">READY</h3>
                        @endif
                    </div>
                </td>

                <td class="title-td">
                    <div class="proforma-title">
                        Proforma de Servicios
                    </div>
                </td>

               <td style="width: 20%;">
    <div class="info-box">
        <div class="no-label">PROFORMA NO.</div>
        {{-- Cambiamos el número fijo por la variable de Firebase --}}
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
                <td class="col" style="padding-left: 50px;">
                    <div class="label">Contacto</div>
                    <div class="value">{{ $contacto ?? 'N/A' }}</div>
                    <div class="label">Teléfono</div>
                    <div class="value">{{ $telefono ?? 'N/A' }}</div>
                </td>
            </tr>
            <tr>
                <td colspan="2">
                    <div class="label">Dirección del Proyecto</div>
                    <div class="value">{{ $direccion ?? 'N/A' }}</div>
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
                <td>{{ $item['desc'] }}</td>
                <td style="text-align: center; font-weight: bold;">{{ $item['cant'] }}</td>
                <td style="text-align: right;">{{ $moneda_simbolo ?? '$' }} {{ number_format($item['precio'], 2) }}</td>
                <td style="text-align: right; font-weight: bold;">{{ $moneda_simbolo ?? '$' }} {{ number_format($item['cant'] * $item['precio'], 2) }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <table class="totals-table">
        <tr>
            <td style="background: #fcfcfc; color: #666; font-weight: bold;">Subtotal</td>
            <td class="text-right">{{ $moneda_simbolo ?? '$' }} {{ number_format($subtotal, 2) }}</td>
        </tr>
        @if($descuento > 0)
        <tr>
            <td style="color: #e63946; font-weight: bold;">Descuento</td>
            <td class="text-right" style="color: #e63946;">- {{ $moneda_simbolo ?? '$' }} {{ number_format($descuento, 2) }}</td>
        </tr>
        @endif
        @if($iva > 0)
        <tr>
            <td style="color: #666;">IVA (15%)</td>
            <td class="text-right">{{ $moneda_simbolo ?? '$' }} {{ number_format($iva, 2) }}</td>
        </tr>
        @endif
        <tr class="total-row">
            <td style="font-size: 12px; letter-spacing: 1px;">TOTAL A PAGAR</td>
            <td class="text-right" style="font-size: 16px;">{{ $moneda_simbolo ?? '$' }} {{ number_format($total, 2) }}</td>
        </tr>
    </table>

    <div class="footer">
        <div class="signature-line"></div>
        <div class="responsable-name">{{ $responsable_nombre ?? 'Jammy Silva' }}</div>
        <div style="font-size: 10px; color: #999; text-transform: uppercase; margin-top: 4px;">
            {{ $responsable_cargo ?? 'Supervisora - Coordinadora' }}
        </div>
        <div style="font-size: 12px; font-weight: bold; margin-top: 10px; color: #333;">
            TEL: {{ $responsable_tel ?? '8588-5337' }}
        </div>
    </div>
</body>
</html>
