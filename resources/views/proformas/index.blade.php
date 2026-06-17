<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Historial de Proformas</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <style>
        /* Toque extra para el estado Facturado (púrpura personalizado) */
        .badge-facturado {
            background-color: #6f42c1 !important;
            color: #fff;
        }
    </style>
</head>
<body class="bg-light p-4">
    <div class="container bg-white p-4 rounded shadow">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>📋 Historial de Proformas Guardadas</h2>
            <a href="{{ url('/') }}" class="btn btn-outline-secondary">⬅️ Volver a la Calculadora</a>
        </div>

        @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        <table class="table table-striped table-hover align-middle">
            <thead class="table-dark">
                <tr>
                    <th>Código</th>
                    <th>Cliente</th>
                    <th>Fecha Emisión</th>
                    <th>Subtotal</th>
                    <th>IVA</th>
                    <th>Total Final</th>
                    <th>Estado</th>
                    <th class="text-center">Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse($proformas as $proforma)
                <tr>
                    <td><strong>{{ $proforma->codigo_proforma }}</strong></td>
                    <td>{{ $proforma->cliente }}</td>
                    <td>
                        {{ $proforma->fecha_emision ? \Carbon\Carbon::parse($proforma->fecha_emision)->format('d/m/Y') : 'N/A' }}
                    </td>
                    <td>${{ number_format($proforma->subtotal, 2) }}</td>
                    <td>${{ number_format($proforma->impuesto, 2) }}</td>
                    <td><strong>${{ number_format($proforma->total, 2) }}</strong></td>
                    <td>
                        {{-- Evaluador de estados dinámico para asignar colores --}}
                        @switch($proforma->estado)
                            @case('Borrador')
                                <span class="badge bg-warning text-dark">🟡 Borrador</span>
                                @break
                            @case('Emitido')
                                <span class="badge bg-primary">🔵 Emitido</span>
                                @break
                            @case('Aprobado')
                                <span class="badge bg-success">🟢 Aprobado</span>
                                @break
                            @case('Facturado')
                                <span class="badge badge-facturado">🟣 Facturado</span>
                                @break
                            @default
                                <span class="badge bg-secondary">{{ $proforma->estado }}</span>
                        @endswitch
                    </td>
                    <td class="text-center">
                        <a href="{{ route('proformas.edit', $proforma->id) }}" class="btn btn-sm btn-primary">✏️ Editar</a>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="8" class="text-center text-muted p-4">No hay proformas registradas en SQL Server aún.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</body>
</html>
