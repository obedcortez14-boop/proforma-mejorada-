<?php
// --- INTEGRACIÓN DE CONTADOR FIREBASE ---
$rutaFirebase = "https://proforma-ready-default-rtdb.firebaseio.com/contador_pdf.json";
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $rutaFirebase);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 5);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
$response = curl_exec($ch);
curl_close($ch);

$contadorFirebase = json_decode($response, true);

if (is_array($contadorFirebase)) {
    $valorContador = $contadorFirebase['contador_pdf'] ?? 1;
} else {
    $valorContador = ($contadorFirebase !== null) ? $contadorFirebase : 1;
}
// Si estamos editando, usamos el código que ya tiene la proforma en SQL Server
$nuevoContador = isset($proforma) && !empty($proforma->codigo_proforma) ? $proforma->codigo_proforma : str_pad((string)$valorContador, 4, "0", STR_PAD_LEFT);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Proforma Ready - Profesional</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body { font-family: 'Helvetica', 'Arial', sans-serif; display: flex; flex-direction: column; align-items: center; min-height: 100vh; background-color: #f1f5f9; padding: 20px; }
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

    <form id="formCotizador" action="{{ isset($proforma) && isset($proforma->id) ? route('proformas.update', $proforma->id) : route('pdf.generar') }}" method="POST" class="w-full flex flex-col items-center">
        @csrf
        @if(isset($proforma))
            @method('PUT')
        @endif

        <input type="hidden" id="input_condiciones_servidor" name="condiciones" value="">

        <div class="no-print w-full max-w-[1000px] mb-6 flex justify-between items-center bg-white p-4 rounded-2xl shadow-sm border border-gray-100">
            <div class="flex items-center gap-6">
                <div class="flex items-center gap-2">
                    <label class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">Moneda</label>
                    <select id="selector-moneda" name="moneda_simbolo" onchange="calcular()" class="bg-gray-50 border border-gray-200 text-gray-900 text-sm rounded-xl p-2.5 font-bold outline-none">
                        <option value="$" {{ isset($proforma) && isset($proforma->moneda) && $proforma->moneda == '$' ? 'selected' : '' }}>Dólares ($)</option>
                        <option value="C$" {{ isset($proforma) && isset($proforma->moneda) && $proforma->moneda == 'C$' ? 'selected' : '' }}>Córdobas (C$)</option>
                    </select>
                </div>
                <div>
                    <a href="{{ route('proformas.index') }}" class="bg-gray-100 text-gray-700 px-4 py-2 rounded-xl text-xs font-bold uppercase tracking-wider hover:bg-gray-200 transition-all">📋 Ver Historial</a>
                </div>
            </div>

            <div class="flex items-center gap-4">
                <label class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">Responsable</label>
                <select id="selector-usuario" name="responsable_nombre" onchange="actualizarFirma()" class="bg-gray-50 border border-gray-200 text-gray-900 text-sm rounded-xl p-2.5 font-bold outline-none">
                    <option value="Jammy Silva" data-cargo="Arquitecta Coordinadora" data-tel="8588-5337" {{ isset($proforma) && isset($proforma->vendedor) && $proforma->vendedor == 'Jammy Silva' ? 'selected' : '' }}>Jammy Silva</option>
                    <option value="Maura Benavides" data-cargo="Ejecutiva de Negocios" data-tel="8560-0648" {{ isset($proforma) && isset($proforma->vendedor) && $proforma->vendedor == 'Maura Benavides' ? 'selected' : '' }}>Maura Benavides</option>
                    <option value="Stephany Mejia" data-cargo="Gerente Comercial" data-tel="8998-0892" {{ isset($proforma) && isset($proforma->vendedor) && $proforma->vendedor == 'Stephany Mejia' ? 'selected' : '' }}>Stephany Mejia</option>
                    <option value="Josep Hernandez" data-cargo="Arquitecto Supervisor" data-tel="8373-2510" {{ isset($proforma) && isset($proforma->vendedor) && $proforma->vendedor == 'Josep Hernandez' ? 'selected' : '' }}>Josep Hernandez</option>
                </select>
            </div>
        </div>

        <div class="proforma-container">
            <div class="bg-[#3d5229] p-8 flex justify-between items-center text-white relative">
                <div class="z-10 bg-white p-3 rounded-2xl shadow-lg flex items-center justify-center min-w-[140px]">
                    <img src="{{ asset('imagen/LOGO JPG.jpg') }}" alt="Logo Ready" class="h-14 w-auto object-contain">
                </div>
                <div class="text-center z-10">
                    <h1 class="text-xl font-extrabold uppercase tracking-[0.3em] border-y border-white/10 py-3">
                        {{ isset($proforma) ? 'Editar Proforma realizada' : 'Proforma de Servicios' }}
                    </h1>
                </div>
                <div class="bg-white/10 backdrop-blur-md text-white p-4 rounded-2xl border border-white/20 text-center min-w-[160px] z-10">
                    <p class="text-[10px] font-bold opacity-70 uppercase tracking-widest mb-1">No. <span class="text-white text-lg font-black block mt-1"><?php echo $nuevoContador; ?></span></p>
                    <input type="hidden" name="numero_proforma" value="<?php echo $nuevoContador; ?>">
                    <p class="text-[9px] font-bold border-t border-white/20 pt-2 mt-1">FECHA: {{ isset($proforma) && isset($proforma->fecha_emision) ? \Carbon\Carbon::parse($proforma->fecha_emision)->format('d/m/Y') : date('d/m/Y') }}</p>
                </div>
            </div>

            <div class="bg-red-600 h-1.5 w-40 mx-auto -mt-0.5 relative z-20 rounded-full shadow-lg"></div>

            <div class="p-12 grid grid-cols-2 gap-x-16 gap-y-8 text-sm">
                <div class="space-y-1.5">
                    <label class="text-[10px] font-extrabold text-[#3d5229] uppercase tracking-wider">Cliente</label>
                    <input type="text" name="cliente" value="{{ isset($proforma) ? $proforma->cliente : '' }}" class="w-full border-b-2 border-gray-100 outline-none focus:border-[#3d5229] py-2 transition-all bg-transparent">
                </div>
                <div class="space-y-1.5">
                    <label class="text-[10px] font-extrabold text-[#3d5229] uppercase tracking-wider">Contacto</label>
                    <input type="text" name="contacto" value="{{ isset($proforma) ? $proforma->observaciones : '' }}" class="w-full border-b-2 border-gray-100 outline-none focus:border-[#3d5229] py-2 transition-all bg-transparent">
                </div>
                <div class="space-y-1.5">
                    <label class="text-[10px] font-extrabold text-[#3d5229] uppercase tracking-wider">RUC / Cédula</label>
                    <input type="text" name="ruc" value="{{ isset($proforma) ? $proforma->ruc_cedula : '' }}" class="w-full border-b-2 border-gray-100 outline-none focus:border-[#3d5229] py-2 transition-all bg-transparent">
                </div>
                <div class="space-y-1.5">
                    <label class="text-[10px] font-extrabold text-[#3d5229] uppercase tracking-wider">Teléfono</label>
                    <input type="text" name="telefono" value="{{ isset($proforma) ? $proforma->telefono : '' }}" class="w-full border-b-2 border-gray-100 outline-none focus:border-[#3d5229] py-2 transition-all bg-transparent">
                </div>
                <div class="space-y-1.5">
                    <label class="text-[10px] font-extrabold text-[#3d5229] uppercase tracking-wider">Dirección del proyecto</label>
                    <input type="text" name="direccion_proyecto" value="{{ isset($proforma) ? $proforma->direccion_proyecto : '' }}" class="w-full border-b-2 border-gray-100 outline-none focus:border-[#3d5229] py-2 transition-all bg-transparent">
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
                        @if(isset($proforma) && isset($proforma->detalles) && $proforma->detalles->count() > 0)
                            @foreach($proforma->detalles as $index => $detalle)
                            <tr>
                                <td class="p-0 cell-height">
                                    <textarea name="items[{{ $index }}][desc]" class="w-full h-full p-5 outline-none resize-none text-[11px] leading-relaxed border-none">{{ $detalle->descripcion }}</textarea>
                                </td>
                                <td class="align-middle text-center bg-gray-50/30">
                                    <input type="number" name="items[{{ $index }}][cant]" value="{{ $detalle->cantidad }}" class="w-full text-center font-bold qty outline-none bg-transparent" oninput="calcular()">
                                </td>
                                <td class="align-middle text-center">
                                    <div class="flex items-center justify-center gap-1">
                                        <span class="symbol font-bold text-gray-400">$</span>
                                        <input type="number" name="items[{{ $index }}][precio]" value="{{ $detalle->precio_unitario }}" class="w-20 text-center font-bold price outline-none bg-transparent" oninput="calcular()">
                                    </div>
                                </td>
                                <td class="align-middle text-center font-black text-gray-700 italic pr-4 subtotal-fila">$ 0</td>
                                <td class="align-middle text-center no-print">
                                    <button type="button" onclick="eliminarFila(this)" class="text-gray-300 hover:text-red-500 transition-colors">✕</button>
                                </td>
                            </tr>
                            @endforeach
                        @else
                            <tr>
                                <td class="p-0 cell-height">
                                    <textarea name="items[0][desc]" class="w-full h-full p-5 outline-none resize-none text-[11px] leading-relaxed border-none" placeholder="Detalles del servicio..."></textarea>
                                </td>
                                <td class="align-middle text-center bg-gray-50/30">
                                    <input type="number" name="items[0][cant]" value="1" class="w-full text-center font-bold qty outline-none bg-transparent" oninput="calcular()">
                                </td>
                                <td class="align-middle text-center">
                                    <div class="flex items-center justify-center gap-1">
                                        <span class="symbol font-bold text-gray-400">$</span>
                                        <input type="number" name="items[0][precio]" value="0" class="w-20 text-center font-bold price outline-none bg-transparent" oninput="calcular()">
                                    </div>
                                </td>
                                <td class="align-middle text-center font-black text-gray-700 italic pr-4 subtotal-fila">$ 0</td>
                                <td class="align-middle text-center no-print">
                                    <button type="button" onclick="eliminarFila(this)" class="text-gray-300 hover:text-red-500 transition-colors">✕</button>
                                </td>
                            </tr>
                        @endif
                    </tbody>
                    <tfoot>
                        <tr class="text-xs">
                            <td colspan="2" rowspan="4" class="p-8 align-top bg-white">
                                <div contenteditable="true"
                                     id="txtCondiciones"
                                     class="border border-dashed border-gray-300 p-6 rounded-2xl bg-gray-50/40 focus:outline-none focus:border-[#3d5229] focus:bg-white focus:ring-4 focus:ring-[#3d5229]/5 transition-all duration-200 group cursor-text"
                                     title="Haz clic en cualquier lugar de este recuadro para editarlo">

                                    <h4 class="font-black text-[#3d5229] uppercase mb-3 text-[11px] tracking-wider select-none group-focus:text-[#4d6635]">
                                        Condiciones:
                                    </h4>

                                    <ul class="text-[10px] text-gray-600 space-y-2 list-disc ml-5 marker:text-[#3d5229]/70">
                                        <li>Vigencia de la cotización: 30 días calendario.</li>
                                        <li>Se requiere el 50% de anticipo para iniciar el proyecto.</li>
                                        <li>El 50% restante se cancelará contra entrega del trabajo.</li>
                                        <li>Tiempo de entrega estimado: 5 a 7 días hábiles.</li>
                                        <li>Precios sujetos a cambios sin previo aviso.</li>
                                    </ul>
                                </div>
                            </td>
                            <td class="p-5 font-bold text-gray-400 uppercase text-[9px] bg-gray-50">Subtotal</td>
                            <td class="p-5 text-right font-black text-gray-800 pr-10 bg-gray-50">
                                <span class="symbol-res">$</span> <span id="txt-subtotal">0</span>
                                <input type="hidden" name="subtotal_val" id="val-subtotal" value="0">
                            </td>
                        </tr>

                        <tr class="text-xs no-print">
                            <td colspan="2" class="p-3 bg-gray-50/50 border-y border-gray-100">
                                <div class="flex justify-around items-center">
                                    <div class="flex items-center gap-3">
                                        <label class="switch"><input type="checkbox" id="switch-desc" onchange="toggleDescuento()"><span class="slider"></span></label>
                                        <span class="text-[9px] font-bold text-blue-600 uppercase">¿Descuento?</span>
                                    </div>
                                    <div class="flex items-center gap-3">
                                        <label class="switch"><input type="checkbox" id="switch-iva" onchange="calcular()" {{ isset($proforma) && isset($proforma->impuesto) && $proforma->impuesto > 0 ? 'checked' : '' }}><span class="slider"></span></label>
                                        <span class="text-[9px] font-bold text-gray-500 uppercase">IVA (15%)</span>
                                    </div>
                                </div>
                            </td>
                        </tr>

                        <tr id="fila-descuento" class="text-xs hidden-discount">
                            <td class="p-5 bg-blue-50/30">
                                <select id="tipo-desc" name="tipo_descuento" class="text-[10px] font-bold bg-transparent outline-none text-blue-600 uppercase" onchange="toggleInputsDescuento()">
                                    <option value="porcentaje">Por Porcentaje (%)</option>
                                    <option value="monto">Por Monto Fijo</option>
                                </select>
                            </td>
                            <td class="p-5 text-right bg-blue-50/30 pr-10">
                                <div id="container-porcentaje" class="flex items-center justify-end">
                                    <input type="number" id="input-porcentaje-desc" class="input-totales w-12 text-blue-600" value="0" oninput="calcularDesdePorcentaje()">
                                    <span class="text-[10px] font-bold text-blue-600">%</span>
                                </div>
                                <div id="container-monto" class="hidden-discount flex items-center justify-end">
                                    <span class="symbol-res text-red-600 font-bold">$</span>
                                    <input type="number" id="input-monto-desc" name="descuento_val" class="input-totales w-24 text-red-600" value="0" step="1" oninput="calcularDesdeMonto()">
                                </div>
                            </td>
                        </tr>

                        <tr class="text-xs">
                            <td class="p-5 font-bold text-gray-400 uppercase text-[9px]">IVA (15%)</td>
                            <td class="p-5 text-right font-bold text-gray-500 pr-10 italic">
                                <span class="symbol-res">$</span> <span id="txt-iva">0</span>
                                <input type="hidden" name="iva_val" id="val-iva" value="0">
                            </td>
                        </tr>

                        <tr class="bg-[#3d5229] text-white">
                            <td colspan="4" class="p-0">
                                <div class="flex items-center justify-center py-6 gap-8">
                                    <span class="font-bold uppercase tracking-[0.4em] text-xs opacity-80">Total a Pagar</span>
                                    <div class="flex items-baseline gap-2 border-l border-white/20 pl-8">
                                        <span class="symbol-res text-xl font-light opacity-70">$</span>
                                        <span id="txt-total" class="font-black text-4xl italic tracking-tighter">0</span>
                                    </div>
                                </div>
                                <input type="hidden" name="total_val" id="val-total" value="0">
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
                <input type="hidden" name="pdf_firma_nombre" id="input-firma-nombre" value="Jammy Silva">
                <input type="hidden" name="pdf_firma_cargo" id="input-firma-cargo" value="Arquitecta Coordinadora">
                <input type="hidden" name="pdf_firma_tel" id="input-firma-tel" value="8588-5337">
            </div>
        </div>

        <div class="no-print flex gap-6 mt-12 mb-20">
            <button type="button" onclick="agregarFila()" class="bg-white border-2 border-[#3d5229] text-[#3d5229] px-8 py-4 rounded-2xl font-bold text-[10px] uppercase tracking-widest hover:bg-[#3d5229] hover:text-white transition-all shadow-lg">➕ Agregar Servicio</button>

            <button type="submit" class="bg-[#3d5229] text-white px-10 py-4 rounded-2xl font-bold text-[10px] uppercase tracking-widest hover:bg-[#2c3d1f] transition-all shadow-2xl">
                {{ isset($proforma) ? '💾 Guardar Cambios y PDF' : '🖨️ Generar PDF' }}
            </button>
        </div>
    </form>

    <script>
        let contadorFila = document.querySelectorAll('#tabla-cuerpo tr').length;

        document.addEventListener("DOMContentLoaded", function() {
            const miFormulario = document.getElementById('formCotizador');

            if (miFormulario) {
                miFormulario.addEventListener('submit', function(e) {
                    const cajaEditable = document.getElementById('txtCondiciones');
                    const inputOculto = document.getElementById('input_condiciones_servidor');

                    if (cajaEditable && inputOculto) {
                        const elementosLista = cajaEditable.querySelectorAll('ul li');
                        let textoFinal = "";

                        if (elementosLista.length > 0) {
                            let lineas = [];
                            elementosLista.forEach(function(li) {
                                let textoLinea = li.innerText.trim();
                                if (textoLinea !== "") {
                                    if (!textoLinea.startsWith('•')) {
                                        textoLinea = "• " + textoLinea;
                                    }
                                    lineas.push(textoLinea);
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
                    calcular();
                });
            }

            actualizarFirma();
            calcular();
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
                <td class="p-0 cell-height"><textarea name="items[${contadorFila}][desc]" class="w-full h-full p-5 outline-none resize-none text-[11px] border-none" placeholder="Descripción..."></textarea></td>
                <td class="align-middle text-center bg-gray-50/30"><input type="number" name="items[${contadorFila}][cant]" value="1" class="w-full text-center font-bold qty outline-none bg-transparent" oninput="calcular()"></td>
                <td class="align-middle text-center">
                    <div class="flex items-center justify-center gap-1">
                        <span class="symbol font-bold text-gray-400 italic">${simbolo}</span>
                        <input type="number" name="items[${contadorFila}][precio]" value="0" class="w-20 text-center font-bold price outline-none bg-transparent" oninput="calcular()">
                    </div>
                </td>
                <td class="align-middle text-center font-black text-gray-700 italic pr-4 subtotal-fila">${simbolo} 0</td>
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
            document.getElementById('input-monto-desc').value = Math.round(subtotal * (porcentaje / 100));
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
                const sub = Math.round(q * p);
                totalBruto += sub;
                row.querySelector('.subtotal-fila').innerText = simbolo + ' ' + sub.toLocaleString('en-US', {minimumFractionDigits: 0, maximumFractionDigits: 0});
            });

            totalBruto = Math.round(totalBruto);
            document.getElementById('val-subtotal').value = totalBruto;
            document.getElementById('txt-subtotal').innerText = totalBruto.toLocaleString('en-US', {minimumFractionDigits: 0, maximumFractionDigits: 0});

            const mntDesc = Math.round(parseFloat(document.getElementById('input-monto-desc').value) || 0);
            const aplicaIva = document.getElementById('switch-iva').checked;
            const baseImponible = Math.max(0, totalBruto - mntDesc);

            const mntIvaExacto = aplicaIva ? (baseImponible * 0.15) : 0;
            const mntIvaMostrar = Math.round(mntIvaExacto);
            const granTotal = Math.round(baseImponible + mntIvaExacto);

            document.getElementById('txt-iva').innerText = mntIvaMostrar.toLocaleString('en-US', {minimumFractionDigits: 0, maximumFractionDigits: 0});
            document.getElementById('txt-total').innerText = granTotal.toLocaleString('en-US', {minimumFractionDigits: 0, maximumFractionDigits: 0});

            document.getElementById('val-iva').value = mntIvaMostrar;
            document.getElementById('val-total').value = granTotal;
        }
    </script>
</body>
</html>
