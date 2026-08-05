<?php
$nuevoContador = isset($proforma) && !empty($proforma->codigo_proforma) ? $proforma->codigo_proforma : "0001";
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Proforma: {{ $proforma->codigo_proforma }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght=400;600;700;800&display=swap');
        body { font-family: 'Inter', sans-serif; display: flex; flex-direction: column; align-items: center; min-height: 100vh; background-color: #f1f5f9; padding: 20px; }
        .proforma-container { width: 100%; max-width: 1000px; background: white; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.05); border-radius: 24px; overflow: hidden; border: 1px solid rgba(226, 232, 240, 0.8); }
        .switch { position: relative; display: inline-block; width: 40px; height: 22px; }
        .switch input { opacity: 0; width: 0; height: 0; }
        .slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #cbd5e1; transition: .4s; border-radius: 34px; }
        .slider:before { position: absolute; content: ""; height: 16px; width: 16px; left: 3px; bottom: 3px; background-color: white; transition: .4s; border-radius: 50%; }
        input:checked + .slider { background-color: #2563eb; }
        input:checked + .slider:before { transform: translateX(18px); }
        table, th, td { border: 1px solid #f1f5f9 !important; }
        .cell-height { height: 130px; }
        .signature-section { display: flex; flex-direction: column; align-items: center; text-align: center; padding-top: 60px; padding-bottom: 80px; }
        .signature-line { width: 250px; border-top: 2px solid #3d5229; margin-bottom: 15px; }
        .input-totales { background: transparent; border-bottom: 1px dashed #cbd5e1; text-align: right; outline: none; font-weight: bold; }
        .hidden-discount { display: none !important; }
        @media print { .no-print { display: none !important; } body { background: white; padding: 0; margin: 0; } .proforma-container { box-shadow: none; border: none; } }
    </style>
</head>
<body>

    @if ($errors->any())
        <div class="no-print w-full max-w-[1000px] mb-4 bg-red-50 border-l-4 border-red-500 p-4 rounded-xl">
            <h3 class="text-sm font-bold text-red-800">Errores del servidor:</h3>
            <ul class="mt-2 text-xs text-red-700 list-disc pl-5">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form id="formCotizador" action="{{ route('proformas.update', $proforma->id) }}" method="POST" novalidate class="w-full flex flex-col items-center">
        @csrf
        @method('PUT')

        <input type="hidden" id="input_condiciones_servidor" name="condiciones" value="{{ $proforma->condiciones }}">

        <div class="no-print w-full max-w-[1000px] mb-6 flex flex-wrap justify-between items-center bg-white p-4 rounded-2xl shadow-sm border border-gray-100 gap-4">
            <div class="flex items-center gap-6">
                <div class="flex items-center gap-2">
                    <label class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">Moneda</label>
                    <select id="selector-moneda" name="moneda_simbolo" onchange="calcular()" class="bg-gray-50 border border-gray-200 text-gray-900 text-sm rounded-xl p-2.5 font-bold outline-none">
                        <option value="$" {{ (isset($proforma->moneda) && $proforma->moneda == '$') || !isset($proforma->moneda) ? 'selected' : '' }}>Dólares ($)</option>
                        <option value="C$" {{ isset($proforma->moneda) && $proforma->moneda == 'C$' ? 'selected' : '' }}>Córdobas (C$)</option>
                    </select>
                </div>
                <div>
                    <a href="{{ route('proformas.index') }}" class="bg-gray-100 text-gray-700 px-4 py-2 rounded-xl text-xs font-bold uppercase tracking-wider hover:bg-gray-200 transition-all">📋 Cancelar y Volver</a>
                </div>
            </div>

            <div class="flex items-center gap-6">
                <div class="flex items-center gap-2">
                    <label class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">Estatus</label>
                    <select id="selector-estado" name="estado" class="bg-gray-50 border border-gray-200 text-gray-900 text-sm rounded-xl p-2.5 font-bold outline-none cursor-pointer">
                        <option value="Borrador" {{ $proforma->estado == 'Borrador' ? 'selected' : '' }}>🟡 Borrador</option>
                        <option value="Emitido" {{ $proforma->estado == 'Emitido' ? 'selected' : '' }}>🔵 Emitido</option>
                        <option value="Aprobado" {{ $proforma->estado == 'Aprobado' ? 'selected' : '' }}>🟢 Aprobado</option>
                        <option value="Facturado" {{ $proforma->estado == 'Facturado' ? 'selected' : '' }}>🟣 Facturado</option>
                    </select>
                </div>

                <div class="flex items-center gap-2">
                    <label class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">Responsable</label>
                    <select id="selector-usuario" name="responsable_nombre" onchange="actualizarFirma()" class="bg-gray-50 border border-gray-200 text-gray-900 text-sm rounded-xl p-2.5 font-bold outline-none">
                        <option value="Jammy Silva" data-cargo="Arquitecta Coordinadora" data-tel="8588-5337" {{ $proforma->vendedor == 'Jammy Silva' ? 'selected' : '' }}>Jammy Silva</option>
                        <option value="Maura Benavides" data-cargo="Ejecutiva de Negocios" data-tel="8560-0648" {{ $proforma->vendedor == 'Maura Benavides' ? 'selected' : '' }}>Maura Benavides</option>
                        <option value="Stephany Mejia" data-cargo="Gerente Comercial" data-tel="8998-0892" {{ $proforma->vendedor == 'Stephany Mejia' ? 'selected' : '' }}>Stephany Mejia</option>
                        <option value="Josep Hernandez" data-cargo="Arquitecto Supervisor" data-tel="8373-2510" {{ $proforma->vendedor == 'Josep Hernandez' ? 'selected' : '' }}>Josep Hernandez</option>
                    </select>
                </div>
            </div>
        </div>

        <div class="proforma-container">
            <div class="bg-[#3d5229] p-8 flex justify-between items-center text-white relative">
                <div class="z-10 bg-white p-3 rounded-2xl shadow-lg flex items-center justify-center min-w-[140px]">
                    <img src="{{ asset('imagen/LOGO JPG.jpg') }}" alt="Logo Ready" class="h-14 w-auto object-contain">
                </div>
                <div class="text-center z-10">
                    <h1 class="text-xl font-extrabold uppercase tracking-[0.3em] border-y border-white/10 py-3">
                        Editar Proforma Realizada
                    </h1>
                </div>
                <div class="bg-white/10 backdrop-blur-md text-white p-4 rounded-2xl border border-white/20 text-center min-w-[160px] z-10">
                    <p class="text-[10px] font-bold opacity-70 uppercase tracking-widest mb-1">No. <span class="text-white text-lg font-black block mt-1"><?php echo $nuevoContador; ?></span></p>
                    <input type="hidden" name="numero_proforma" value="<?php echo $nuevoContador; ?>">

                    <!-- CAMBIO DE FECHA DE EMISIÓN -->
                    <div class="border-t border-white/20 pt-2 mt-1">
                        <label class="block text-[8px] font-bold opacity-80 uppercase tracking-widest mb-0.5">FECHA:</label>
                        <input type="date" name="fecha_emision" value="{{ isset($proforma->fecha_emision) ? \Carbon\Carbon::parse($proforma->fecha_emision)->format('Y-m-d') : date('Y-m-d') }}" class="bg-transparent text-white font-bold text-xs text-center outline-none border border-white/30 rounded-lg px-1 py-0.5 w-full cursor-pointer hover:bg-white/10 focus:bg-white/20 transition-all">
                    </div>
                </div>
            </div>

            <div class="bg-red-600 h-1.5 w-40 mx-auto -mt-0.5 relative z-20 rounded-full shadow-lg"></div>

            <div class="p-12 grid grid-cols-2 gap-x-16 gap-y-8 text-sm">
                <div class="space-y-1.5">
                    <label class="text-[10px] font-extrabold text-[#3d5229] uppercase tracking-wider">Cliente</label>
                    <input type="text" name="cliente" value="{{ $proforma->cliente }}" class="w-full border-b-2 border-gray-100 outline-none focus:border-[#3d5229] py-2 transition-all bg-transparent">
                </div>
                <div class="space-y-1.5">
                    <label class="text-[10px] font-extrabold text-[#3d5229] uppercase tracking-wider">Contacto / Observaciones</label>
                    <input type="text" name="contacto" value="{{ $proforma->observaciones }}" class="w-full border-b-2 border-gray-100 outline-none focus:border-[#3d5229] py-2 transition-all bg-transparent">
                </div>
                <div class="space-y-1.5">
                    <label class="text-[10px] font-extrabold text-[#3d5229] uppercase tracking-wider">RUC / Cédula</label>
                    <input type="text" name="ruc" value="{{ $proforma->ruc_cedula }}" class="w-full border-b-2 border-gray-100 outline-none focus:border-[#3d5229] py-2 transition-all bg-transparent">
                </div>
                <div class="space-y-1.5">
                    <label class="text-[10px] font-extrabold text-[#3d5229] uppercase tracking-wider">Teléfono</label>
                    <input type="text" name="telefono" value="{{ $proforma->telefono }}" class="w-full border-b-2 border-gray-100 outline-none focus:border-[#3d5229] py-2 transition-all bg-transparent">
                </div>
                <div class="col-span-2 space-y-1.5">
                    <label class="text-[10px] font-extrabold text-[#3d5229] uppercase tracking-wider">Dirección del Proyecto</label>
                    <input type="text" name="direccion_proyecto" value="{{ $proforma->direccion_proyecto }}" class="w-full border-b-2 border-gray-100 outline-none focus:border-[#3d5229] py-2 transition-all bg-transparent">
                </div>
            </div>

            <div class="px-10">
                <table class="w-full border-collapse rounded-xl overflow-hidden">
                    <thead>
                        <tr class="bg-[#3d5229] text-white text-[9px] uppercase tracking-[0.2em]">
                            <th class="p-5 text-left w-[50%]">Descripción Detallada</th>
                            <th class="p-5 text-center w-[12%]">Cant.</th>
                            <th class="p-5 text-center w-[18%]">P. Unitario</th>
                            <th class="p-5 text-center w-[20%]">Subtotal</th>
                            <th class="p-5 no-print w-10"></th>
                        </tr>
                    </thead>
                    <tbody id="tabla-cuerpo">
                        @foreach($proforma->detalles as $index => $detalle)
                        <tr>
                            <input type="hidden" name="items[{{ $index }}][id]" value="{{ $detalle->id }}">
                            <td class="p-0 cell-height">
                                <textarea name="items[{{ $index }}][desc]" class="w-full h-full p-5 outline-none resize-none text-[11px] leading-relaxed border-none">{{ $detalle->descripcion }}</textarea>
                            </td>
                            <td class="align-middle text-center bg-gray-50/30">
                                <input type="number" name="items[{{ $index }}][cant]" value="{{ $detalle->cantidad }}" class="w-full text-center font-bold qty outline-none bg-transparent" oninput="calcular()">
                            </td>
                            <td class="align-middle text-center">
                                <div class="flex items-center justify-center gap-1">
                                    <span class="symbol font-bold text-gray-400">$</span>
                                    <input type="number" step="0.01" name="items[{{ $index }}][precio]" value="{{ $detalle->precio_unitario }}" class="w-20 text-center font-bold price outline-none bg-transparent" oninput="calcular()">
                                </div>
                            </td>
                            <td class="align-middle text-center font-black text-gray-700 italic pr-4 subtotal-fila">$ {{ number_format($detalle->subtotal, 2, '.', '') }}</td>
                            <td class="align-middle text-center no-print">
                                <button type="button" onclick="eliminarFila(this)" class="text-gray-300 hover:text-red-500 transition-colors">✕</button>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr class="text-xs">
                            <td colspan="2" rowspan="4" class="p-8 align-top bg-white">
                                <div contenteditable="true" id="txtCondiciones" class="border border-dashed border-gray-300 p-6 rounded-2xl bg-gray-50/40 focus:outline-none focus:border-[#3d5229] focus:bg-white transition-all duration-200 cursor-text">
                                    <h4 class="font-black text-[#3d5229] uppercase mb-3 text-[11px] tracking-wider select-none">Condiciones:</h4>
                                    @if(!empty($proforma->condiciones))
                                        <ul class="text-[10px] text-gray-600 space-y-2 list-disc ml-5 marker:text-[#3d5229]/70">
                                            @foreach(explode("\n", $proforma->condiciones) as $linea)
                                                @if(trim($linea) != "")
                                                    <li>{{ ltrim($linea, '• ') }}</li>
                                                @endif
                                            @endforeach
                                        </ul>
                                    @else
                                        <ul class="text-[10px] text-gray-600 space-y-2 list-disc ml-5 marker:text-[#3d5229]/70">
                                            <li>Vigencia de la cotización: 30 días calendario.</li>
                                            <li>Se requiere el 50% de anticipo para iniciar el proyecto.</li>
                                            <li>Se requiere el 50% de anticipo para iniciar el proyecto.</li>
                                        </ul>
                                    @endif
                                </div>
                            </td>
                            <td class="p-5 font-bold text-gray-400 uppercase text-[9px] bg-gray-50">Subtotal</td>
                            <td class="p-5 text-right font-black text-gray-800 pr-10 bg-gray-50">
                                <span class="symbol-res">$</span> <span id="txt-subtotal">{{ $proforma->subtotal }}</span>
                                <input type="hidden" name="subtotal_val" id="val-subtotal" value="{{ $proforma->subtotal }}">
                            </td>
                        </tr>

                        <tr class="text-xs no-print">
                            <td colspan="2" class="p-3 bg-gray-50/50 border-y border-gray-100">
                                <div class="flex justify-around items-center">
                                    <div class="flex items-center gap-3">
                                        <label class="switch">
                                            <input type="checkbox" id="switch-desc" onchange="toggleDescuento()" {{ (isset($proforma->descuento) && $proforma->descuento > 0) ? 'checked' : '' }}>
                                            <span class="slider"></span>
                                        </label>
                                        <span class="text-[9px] font-bold text-blue-600 uppercase">¿Descuento?</span>
                                    </div>
                                    <div class="flex items-center gap-3">
                                        <label class="switch">
                                            <input type="checkbox" id="switch-iva" onchange="calcular()" {{ (isset($proforma->impuesto) && $proforma->impuesto > 0) ? 'checked' : '' }}>
                                            <span class="slider"></span>
                                        </label>
                                        <span class="text-[9px] font-bold text-gray-500 uppercase">IVA (15%)</span>
                                    </div>
                                </div>
                            </td>
                        </tr>

                        <tr id="fila-descuento" class="text-xs {{ (isset($proforma->descuento) && $proforma->descuento > 0) ? '' : 'hidden-discount' }}">
                            <td class="p-5 bg-blue-50/30">
                                <select id="tipo-desc" class="text-[10px] font-bold bg-transparent outline-none text-blue-600 uppercase" onchange="toggleInputsDescuento()">
                                    <option value="monto" selected>Por Monto Fijo</option>
                                    <option value="porcentaje">Por Porcentaje (%)</option>
                                </select>
                            </td>
                            <td class="p-5 text-right bg-blue-50/30 pr-10">
                                <div id="container-porcentaje" class="hidden-discount flex items-center justify-end">
                                    <input type="number" id="input-porcentaje-desc" class="input-totales w-12 text-blue-600" value="0" oninput="calcularDesdePorcentaje()">
                                    <span class="text-[10px] font-bold text-blue-600">%</span>
                                </div>
                                <div id="container-monto" class="flex items-center justify-end">
                                    <span class="symbol-res text-red-600 font-bold">$</span>
                                    <input type="number" step="0.01" id="input-monto-desc" name="descuento_val" class="input-totales w-24 text-red-600" value="{{ $proforma->descuento ?? 0 }}" oninput="calcularDesdeMonto()">
                                </div>
                            </td>
                        </tr>

                        <tr>
                            <td class="p-5 font-bold text-gray-400 uppercase text-[9px]">IVA (15%)</td>
                            <td class="p-5 text-right font-bold text-gray-500 pr-10 italic">
                                <span class="symbol-res">$</span> <span id="txt-iva">{{ $proforma->impuesto }}</span>
                                <input type="hidden" name="iva_val" id="val-iva" value="{{ $proforma->impuesto }}">
                            </td>
                        </tr>

                        <tr class="bg-[#3d5229] text-white">
                            <td colspan="4" class="p-0">
                                <div class="flex items-center justify-center py-6 gap-8">
                                    <span class="font-bold uppercase tracking-[0.4em] text-xs opacity-80">Total Modificado</span>
                                    <div class="flex items-baseline gap-2 border-l border-white/20 pl-8">
                                        <span class="symbol-res text-xl font-light opacity-70">$</span>
                                        <span id="txt-total" class="font-black text-4xl italic tracking-tighter">{{ $proforma->total }}</span>
                                    </div>
                                </div>
                                <input type="hidden" name="total_val" id="val-total" value="{{ $proforma->total }}">
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="signature-section">
                <div class="signature-line"></div>
                <p id="firma-nombre" class="font-extrabold text-[#3d5229] text-lg uppercase tracking-widest">Jammy Silva</p>
                <p id="firma-cargo" class="text-[10px] text-gray-400 italic font-bold uppercase">Arquitecta Coordinadora</p>
                <p id="firma-tel" class="text-[10px] font-black mt-2 tracking-[0.2em] text-gray-600">TEL: 8588-5337</p>
                <input type="hidden" name="pdf_firma_nombre" id="input-firma-nombre" value="">
                <input type="hidden" name="pdf_firma_cargo" id="input-firma-cargo" value="">
                <input type="hidden" name="pdf_firma_tel" id="input-firma-tel" value="">
            </div>
        </div>

        <div class="no-print flex gap-6 mt-12 mb-20">
            <button type="button" onclick="agregarFila()" class="bg-white border-2 border-[#3d5229] text-[#3d5229] px-8 py-4 rounded-2xl font-bold text-[10px] uppercase tracking-widest hover:bg-[#3d5229] hover:text-white transition-all shadow-lg">➕ Agregar Servicio</button>
            <button type="submit" class="bg-blue-600 text-white px-10 py-4 rounded-2xl font-bold text-[10px] uppercase tracking-widest hover:bg-blue-700 transition-all shadow-2xl">💾 Guardar Cambios</button>
        </div>
    </form>

    <script>
        let contadorFila = document.querySelectorAll('#tabla-cuerpo tr').length;

        document.addEventListener("DOMContentLoaded", function() {
            actualizarFirma();
            calcular();
        });

        // Evento directo al enviar el formulario
        document.getElementById('formCotizador').addEventListener('submit', function(e) {
            const cajaEditable = document.getElementById('txtCondiciones');
            const inputOculto = document.getElementById('input_condiciones_servidor');

            if (cajaEditable && inputOculto) {
                const elementosLista = cajaEditable.querySelectorAll('ul li');
                let textoFinal = "";

                if (elementosLista.length > 0) {
                    let lineas = [];
                    elementosLista.forEach(function(li) {
                        let textLinea = li.innerText.trim();
                        if (textLinea !== "") {
                            if (!textLinea.startsWith('•')) textLinea = "• " + textLinea;
                            lineas.push(textLinea);
                        }
                    });
                    textoFinal = lineas.join("\n");
                } else {
                    const clon = cajaEditable.cloneNode(true);
                    const tituloH4 = clon.querySelector('h4');
                    if (tituloH4) tituloH4.remove();
                    textoFinal = clon.innerText.trim();
                }
                inputOculto.value = textoFinal;
            }
        });

        function actualizarFirma() {
            const select = document.getElementById('selector-usuario');
            if(!select) return;
            const option = select.options[select.selectedIndex];
            document.getElementById('firma-nombre').innerText = option.value;
            document.getElementById('firma-cargo').innerText = option.getAttribute('data-cargo');
            document.getElementById('firma-tel').innerText = 'TEL: ' + option.getAttribute('data-tel');
            document.getElementById('input-firma-nombre').value = option.value;
            document.getElementById('input-firma-cargo').value = option.getAttribute('data-cargo');
            document.getElementById('input-firma-tel').value = option.getAttribute('data-tel');
        }

        function agregarFila() {
            const tbody = document.getElementById('tabla-cuerpo');
            const simbolo = document.getElementById('selector-moneda').value;

            const row = `<tr>
                <input type="hidden" name="items[${contadorFila}][id]" value="new">
                <td class="p-0 cell-height"><textarea name="items[${contadorFila}][desc]" class="w-full h-full p-5 outline-none resize-none text-[11px] border-none" placeholder="Escriba la descripción del servicio..."></textarea></td>
                <td class="align-middle text-center bg-gray-50/30"><input type="number" name="items[${contadorFila}][cant]" value="1" class="w-full text-center font-bold qty outline-none bg-transparent" oninput="calcular()"></td>
                <td class="align-middle text-center">
                    <div class="flex items-center justify-center gap-1">
                        <span class="symbol font-bold text-gray-400">${simbolo}</span>
                        <input type="number" step="0.01" name="items[${contadorFila}][precio]" value="0" class="w-20 text-center font-bold price outline-none bg-transparent" oninput="calcular()">
                    </div>
                </td>
                <td class="align-middle text-center font-black text-gray-700 italic pr-4 subtotal-fila">${simbolo} 0.00</td>
                <td class="align-middle text-center no-print"><button type="button" onclick="eliminarFila(this)" class="text-gray-300 hover:text-red-500 transition-colors">✕</button></td>
            </tr>`;
            tbody.insertAdjacentHTML('beforeend', row);
            contadorFila++;
            calcular();
        }

        function eliminarFila(btn) {
            if(document.querySelectorAll('#tabla-cuerpo tr').length > 1) {
                btn.closest('tr').remove();
            }
            calcular();
        }

        function toggleDescuento() {
            const activo = document.getElementById('switch-desc').checked;
            const filaDesc = document.getElementById('fila-descuento');
            if(!activo) {
                filaDesc.classList.add('hidden-discount');
                document.getElementById('input-monto-desc').value = 0;
                document.getElementById('input-porcentaje-desc').value = 0;
            } else {
                filaDesc.classList.remove('hidden-discount');
            }
            calcular();
        }

        function toggleInputsDescuento() {
            const tipo = document.getElementById('tipo-desc').value;
            document.getElementById('container-monto').classList.toggle('hidden-discount', tipo === 'porcentaje');
            document.getElementById('container-porcentaje').classList.toggle('hidden-discount', tipo === 'monto');
            calcular();
        }

        function calcularDesdePorcentaje() {
            const subtotal = parseFloat(document.getElementById('val-subtotal').value) || 0;
            const porcentaje = parseFloat(document.getElementById('input-porcentaje-desc').value) || 0;
            document.getElementById('input-monto-desc').value = (subtotal * (porcentaje / 100)).toFixed(2);
            calcular();
        }

        function calcularDesdeMonto() {
            const subtotal = parseFloat(document.getElementById('val-subtotal').value) || 0;
            const monto = parseFloat(document.getElementById('input-monto-desc').value) || 0;
            document.getElementById('input-porcentaje-desc').value = subtotal > 0 ? ((monto / subtotal) * 100).toFixed(0) : 0;
            calcular();
        }

        function calcular() {
            const selector = document.getElementById('selector-moneda');
            if(!selector) return;
            const simbolo = selector.value;
            let totalBruto = 0;

            document.querySelectorAll('.symbol').forEach(s => s.innerText = simbolo);
            document.querySelectorAll('.symbol-res').forEach(s => s.innerText = simbolo);

            document.querySelectorAll('#tabla-cuerpo tr').forEach(row => {
                const qtyInput = row.querySelector('.qty');
                const priceInput = row.querySelector('.price');
                if(!qtyInput || !priceInput) return;

                const q = parseFloat(qtyInput.value) || 0;
                const p = parseFloat(priceInput.value) || 0;
                const sub = q * p;
                totalBruto += sub;
                row.querySelector('.subtotal-fila').innerText = simbolo + ' ' + sub.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
            });

            document.getElementById('val-subtotal').value = totalBruto.toFixed(2);
            document.getElementById('txt-subtotal').innerText = totalBruto.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});

            const mntDesc = parseFloat(document.getElementById('input-monto-desc').value) || 0;
            const aplicaIva = document.getElementById('switch-iva').checked;
            const baseImponible = Math.max(0, totalBruto - mntDesc);

            const mntIva = aplicaIva ? (baseImponible * 0.15) : 0;
            const granTotal = baseImponible + mntIva;

            document.getElementById('txt-iva').innerText = mntIva.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
            document.getElementById('txt-total').innerText = granTotal.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});

            document.getElementById('val-iva').value = mntIva.toFixed(2);
            document.getElementById('val-total').value = granTotal.toFixed(2);
        }
    </script>
</body>
</html>
