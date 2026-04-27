<?php
// --- INTEGRACIÓN DE CONTADOR FIREBASE (PROYECTO READY) ---
$rutaFirebase = "https://proforma-ready-default-rtdb.firebaseio.com/contador_pdf.json";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $rutaFirebase);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 5);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Añadido para evitar problemas de SSL en local
$response = curl_exec($ch);
curl_close($ch);

$contadorFirebase = json_decode($response, true);

/**
 * EXPLICACIÓN DEL FIX:
 * Firebase puede devolver el valor directo (1) o un array si la ruta no es exacta.
 * Forzamos que sea un string y validamos si es array.
 */
if (is_array($contadorFirebase)) {
    // Si es un array, intentamos sacar el valor o por defecto 1
    $valorContador = $contadorFirebase['contador_pdf'] ?? 1;
} else {
    // Si no es array, usamos el valor directo o 1 si es null
    $valorContador = ($contadorFirebase !== null) ? $contadorFirebase : 1;
}

// Convertimos a string explícitamente para evitar el TypeError
$nuevoContador = str_pad((string)$valorContador, 4, "0", STR_PAD_LEFT);
// ---------------------------------------------------------
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Proforma Ready - Profesional</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap');

        body {
            font-family: 'Inter', sans-serif;
            display: flex;
            flex-direction: column;
            align-items: center;
            min-height: 100vh;
            background-color: #f1f5f9;
            padding: 20px;
        }

        .proforma-container {
            width: 100%;
            max-width: 1000px;
            background: white;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.05);
            border-radius: 24px;
            overflow: hidden;
            border: 1px solid rgba(226, 232, 240, 0.8);
        }

        .switch { position: relative; display: inline-block; width: 40px; height: 22px; }
        .switch input { opacity: 0; width: 0; height: 0; }
        .slider {
            position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0;
            background-color: #cbd5e1; transition: .4s; border-radius: 34px;
        }
        .slider:before {
            position: absolute; content: ""; height: 16px; width: 16px; left: 3px; bottom: 3px;
            background-color: white; transition: .4s; border-radius: 50%;
        }
        input:checked + .slider { background-color: #2563eb; }
        input:checked + .slider:before { transform: translateX(18px); }

        table, th, td { border: 1px solid #f1f5f9 !important; }
        .cell-height { height: 130px; }

        .signature-section {
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            padding-top: 60px;
            padding-bottom: 80px;
        }
        .signature-line {
            width: 250px;
            border-top: 2px solid #3d5229;
            margin-bottom: 15px;
        }

        .input-totales {
            background: transparent;
            border-bottom: 1px dashed #cbd5e1;
            text-align: right;
            outline: none;
            font-weight: bold;
        }
        .input-totales:focus { border-bottom-color: #3d5229; }

        .hidden-discount {
            display: none !important;
        }

        @media print {
            .no-print { display: none !important; }
            body { background: white; padding: 0; margin: 0; display: block; }
            .proforma-container { margin: 0; box-shadow: none; max-width: 100%; border: none; border-radius: 0; }
            select { appearance: none; border: none; background: transparent; }
        }
    </style>
</head>
<body>

    <form action="{{ route('pdf.generar') }}" method="POST" class="w-full flex flex-col items-center">
        @csrf

        <div class="no-print w-full max-w-[1000px] mb-6 flex justify-between items-center bg-white p-4 rounded-2xl shadow-sm border border-gray-100">
            <div class="flex items-center gap-6">
                <div class="flex items-center gap-2">
                    <label class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">Moneda</label>
                    <select id="selector-moneda" name="moneda_simbolo" onchange="calcular()" class="bg-gray-50 border border-gray-200 text-gray-900 text-sm rounded-xl p-2.5 font-bold outline-none">
                        <option value="$">Dólares ($)</option>
                        <option value="C$">Córdobas (C$)</option>
                    </select>
                </div>
            </div>

            <div class="flex items-center gap-4">
                <label class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">Responsable</label>
                <select id="selector-usuario" name="responsable_nombre" onchange="actualizarFirma()" class="bg-gray-50 border border-gray-200 text-gray-900 text-sm rounded-xl p-2.5 font-bold outline-none">
                    <option value="Jammy Silva" data-cargo="Arquitecta Coordinadora" data-tel="8588-5337">Jammy Silva</option>
                    <option value="Owen Rodriguez" data-cargo="Arquitecto Supervisor" data-tel="58859291">Owen Rodriguez</option>
                    <option value="Stefany Mejia" data-cargo="Jefa de Ventas" data-tel="8998-0892">Stefany Mejia</option>
                    <option value="Henrry Gutierrez" data-cargo="Representante de Ventas" data-tel="82529465">Henry Gutierrez</option>
                </select>
            </div>
        </div>

        <div class="proforma-container">
            <div class="bg-[#3d5229] p-8 flex justify-between items-center text-white relative">
                <div class="z-10 bg-white p-3 rounded-2xl shadow-lg flex items-center justify-center min-w-[140px]">
                    <img src="{{ asset('imagen/LOGO JPG.jpg') }}" alt="Logo Ready" class="h-14 w-auto object-contain">
                </div>
                <div class="text-center z-10">
                    <h1 class="text-xl font-extrabold uppercase tracking-[0.3em] border-y border-white/10 py-3">Proforma de Servicios</h1>
                </div>
                <div class="bg-white/10 backdrop-blur-md text-white p-4 rounded-2xl border border-white/20 text-center min-w-[160px] z-10">
                    <p class="text-[10px] font-bold opacity-70 uppercase tracking-widest mb-1">No. <span class="text-white text-lg font-black block mt-1"><?php echo $nuevoContador; ?></span></p>
                    <input type="hidden" name="numero_proforma" value="<?php echo $nuevoContador; ?>">
                    <p class="text-[9px] font-bold border-t border-white/20 pt-2 mt-1">FECHA: <?php echo date('d/m/Y'); ?></p>
                </div>
            </div>

            <div class="bg-red-600 h-1.5 w-40 mx-auto -mt-0.5 relative z-20 rounded-full shadow-lg"></div>

            <div class="p-12 grid grid-cols-2 gap-x-16 gap-y-8 text-sm">
                <div class="space-y-1.5">
                    <label class="text-[10px] font-extrabold text-[#3d5229] uppercase tracking-wider">Cliente</label>
                    <input type="text" name="cliente" class="w-full border-b-2 border-gray-100 outline-none focus:border-[#3d5229] py-2 transition-all bg-transparent" placeholder="Nombre del cliente">
                </div>
                <div class="space-y-1.5">
                    <label class="text-[10px] font-extrabold text-[#3d5229] uppercase tracking-wider">Contacto</label>
                    <input type="text" name="contacto" class="w-full border-b-2 border-gray-100 outline-none focus:border-[#3d5229] py-2 transition-all bg-transparent" placeholder="Nombre de contacto">
                </div>
                <div class="space-y-1.5">
                    <label class="text-[10px] font-extrabold text-[#3d5229] uppercase tracking-wider">RUC / Cédula</label>
                    <input type="text" name="ruc" class="w-full border-b-2 border-gray-100 outline-none focus:border-[#3d5229] py-2 transition-all bg-transparent">
                </div>
                <div class="space-y-1.5">
                    <label class="text-[10px] font-extrabold text-[#3d5229] uppercase tracking-wider">Teléfono</label>
                    <input type="text" name="telefono" class="w-full border-b-2 border-gray-100 outline-none focus:border-[#3d5229] py-2 transition-all bg-transparent">
                </div>
                <div class="col-span-2 space-y-1.5">
                    <label class="text-[10px] font-extrabold text-[#3d5229] uppercase tracking-wider">Dirección del Cliente</label>
                    <input type="text" name="direccion" class="w-full border-b-2 border-gray-100 outline-none focus:border-[#3d5229] py-2 transition-all bg-transparent">
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
                        <tr>
                            <td class="p-0 cell-height">
                                <textarea name="items[0][desc]" class="w-full h-full p-5 outline-none resize-none text-[11px] leading-relaxed" placeholder="Detalles del servicio..."></textarea>
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
                            <td class="align-middle text-center font-black text-gray-700 italic pr-4 subtotal-fila">$ 0.00</td>
                            <td class="align-middle text-center no-print">
                                <button type="button" onclick="eliminarFila(this)" class="text-gray-300 hover:text-red-500 transition-colors">✕</button>
                            </td>
                        </tr>
                    </tbody>
                    <tfoot>
                        <tr class="text-xs">
                            <td colspan="2" rowspan="4" class="p-8 align-top bg-white">
                                <div class="border border-dashed border-gray-200 p-5 rounded-2xl bg-gray-50/50">
                                    <h4 class="font-extrabold text-[#3d5229] uppercase mb-2 text-[10px] tracking-tighter">Condiciones:</h4>
                                    <ul class="text-[10px] text-gray-500 space-y-1 list-disc ml-4">
                                        <li>Vigencia: 30 días.</li>
                                        <li>Se requiere el 50% de anticipo.</li>
                                    </ul>
                                </div>
                            </td>
                            <td class="p-5 font-bold text-gray-400 uppercase text-[9px] bg-gray-50">Subtotal</td>
                            <td class="p-5 text-right font-black text-gray-800 pr-10 bg-gray-50">
                                <span class="symbol-res">$</span> <span id="txt-subtotal">0.00</span>
                                <input type="hidden" name="subtotal_val" id="val-subtotal">
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
                                        <label class="switch"><input type="checkbox" id="switch-iva" onchange="calcular()"><span class="slider"></span></label>
                                        <span class="text-[9px] font-bold text-gray-500 uppercase">IVA (15%)</span>
                                    </div>
                                </div>
                            </td>
                        </tr>

                        <tr id="fila-descuento" class="text-xs hidden-discount">
                            <td class="p-5 bg-blue-50/30">
                                <div class="flex flex-col gap-1">
                                    <label class="text-[8px] font-black text-blue-400 uppercase">Tipo Descuento</label>
                                    <select id="tipo-desc" name="tipo_descuento" class="text-[10px] font-bold bg-transparent outline-none text-blue-600 uppercase" onchange="toggleInputsDescuento()">
                                        <option value="porcentaje">Por Porcentaje (%)</option>
                                        <option value="monto">Por Monto Fijo</option>
                                    </select>
                                </div>
                            </td>
                            <td class="p-5 text-right bg-blue-50/30 pr-10">
                                <div id="container-porcentaje" class="flex items-center justify-end gap-1">
                                    <span class="text-blue-600 font-bold">-</span>
                                    <input type="number" id="input-porcentaje-desc" class="input-totales w-12 text-blue-600" value="0" oninput="calcularDesdePorcentaje()">
                                    <span class="text-[10px] font-bold text-blue-600">%</span>
                                </div>
                                <div id="container-monto" class="hidden-discount flex items-center justify-end gap-1">
                                    <span class="text-red-600 font-bold">-</span>
                                    <span class="symbol-res text-red-600 font-bold">$</span>
                                    <input type="number" id="input-monto-desc" name="descuento_val" class="input-totales w-24 text-red-600" value="0.00" step="0.01" oninput="calcularDesdeMonto()">
                                </div>
                            </td>
                        </tr>

                        <tr class="text-xs">
                            <td class="p-5 font-bold text-gray-400 uppercase text-[9px]">IVA (15%)</td>
                            <td class="p-5 text-right font-bold text-gray-500 pr-10 italic">
                                <span class="symbol-res">$</span> <span id="txt-iva">0.00</span>
                                <input type="hidden" name="iva_val" id="val-iva">
                            </td>
                        </tr>

                        <tr class="bg-[#3d5229] text-white">
                            <td colspan="4" class="p-0">
                                <div class="flex items-center justify-center py-6 gap-8">
                                    <span class="font-bold uppercase tracking-[0.4em] text-xs opacity-80">Total a Pagar</span>
                                    <div class="flex items-baseline gap-2 border-l border-white/20 pl-8">
                                        <span class="symbol-res text-xl font-light opacity-70">$</span>
                                        <span id="txt-total" class="font-black text-4xl italic tracking-tighter">0.00</span>
                                    </div>
                                </div>
                                <input type="hidden" name="total_val" id="val-total">
                            </td>
                        </tr>
                    </tfoot>
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
            <button type="button" onclick="agregarFila()" class="bg-white border-2 border-[#3d5229] text-[#3d5229] px-8 py-4 rounded-2xl font-bold text-[10px] uppercase tracking-widest hover:bg-[#3d5229] hover:text-white transition-all shadow-lg">
                ➕ Agregar Servicio
            </button>
            <button type="submit" class="bg-[#3d5229] text-white px-10 py-4 rounded-2xl font-bold text-[10px] uppercase tracking-widest hover:bg-[#2c3d1f] transition-all shadow-2xl">
                🖨️ Generar PDF
            </button>
        </div>
    </form>

    <script>
        let contadorFila = 1;

        function actualizarFirma() {
            const select = document.getElementById('selector-usuario');
            const option = select.options[select.selectedIndex];
            const nombre = option.value;
            const cargo = option.getAttribute('data-cargo');
            const tel = option.getAttribute('data-tel');

            document.getElementById('firma-nombre').innerText = nombre;
            document.getElementById('firma-cargo').innerText = cargo;
            document.getElementById('firma-tel').innerText = 'TEL: ' + tel;

            document.getElementById('input-firma-nombre').value = nombre;
            document.getElementById('input-firma-cargo').value = cargo;
            document.getElementById('input-firma-tel').value = tel;
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
                <td class="align-middle text-center font-black text-gray-700 italic pr-4 subtotal-fila">${simbolo} 0.00</td>
                <td class="align-middle text-center no-print"><button type="button" onclick="eliminarFila(this)" class="text-gray-300 hover:text-red-500 transition-colors">✕</button></td>
            </tr>`;
            tbody.insertAdjacentHTML('beforeend', row);
            contadorFila++;
            calcular();
        }

        function eliminarFila(btn) {
            if(document.querySelectorAll('#tabla-cuerpo tr').length > 1) btn.closest('tr').remove();
            calcular();
        }

        function toggleDescuento() {
            const activo = document.getElementById('switch-desc').checked;
            const filaDesc = document.getElementById('fila-descuento');

            if(!activo) {
                filaDesc.classList.add('hidden-discount');
                document.getElementById('input-porcentaje-desc').value = 0;
                document.getElementById('input-monto-desc').value = 0;
            } else {
                filaDesc.classList.remove('hidden-discount');
                toggleInputsDescuento();
            }
            calcular();
        }

        function toggleInputsDescuento() {
            const tipo = document.getElementById('tipo-desc').value;
            const contMonto = document.getElementById('container-monto');
            const contPorc = document.getElementById('container-porcentaje');

            if(tipo === 'porcentaje') {
                contMonto.classList.add('hidden-discount');
                contPorc.classList.remove('hidden-discount');
            } else {
                contPorc.classList.add('hidden-discount');
                contMonto.classList.remove('hidden-discount');
            }
            calcular();
        }

        function calcularDesdePorcentaje() {
            const subtotal = parseFloat(document.getElementById('val-subtotal').value) || 0;
            const porcentaje = parseFloat(document.getElementById('input-porcentaje-desc').value) || 0;
            const monto = subtotal * (porcentaje / 100);
            document.getElementById('input-monto-desc').value = monto.toFixed(2);
            calcular();
        }

        function calcularDesdeMonto() {
            const subtotal = parseFloat(document.getElementById('val-subtotal').value) || 0;
            const monto = parseFloat(document.getElementById('input-monto-desc').value) || 0;
            const porcentaje = subtotal > 0 ? (monto / subtotal) * 100 : 0;
            document.getElementById('input-porcentaje-desc').value = porcentaje.toFixed(0);
            calcular();
        }

        function calcular() {
            const simbolo = document.getElementById('selector-moneda').value;
            let totalBruto = 0;

            document.querySelectorAll('.symbol').forEach(s => s.innerText = simbolo);
            document.querySelectorAll('.symbol-res').forEach(s => s.innerText = simbolo);
            document.getElementById('moneda-nombre').innerText = simbolo === '$' ? 'Dólares Americanos' : 'Córdobas Netos';

            document.querySelectorAll('#tabla-cuerpo tr').forEach(row => {
                const q = parseFloat(row.querySelector('.qty').value) || 0;
                const p = parseFloat(row.querySelector('.price').value) || 0;
                const sub = q * p;
                totalBruto += sub;
                row.querySelector('.subtotal-fila').innerText = simbolo + ' ' + sub.toLocaleString('en-US', {minimumFractionDigits: 2});
            });

            document.getElementById('val-subtotal').value = totalBruto;
            document.getElementById('txt-subtotal').innerText = totalBruto.toLocaleString('en-US', {minimumFractionDigits: 2});

            const aplicaDesc = document.getElementById('switch-desc').checked;
            let mntDesc = 0;

            if(aplicaDesc) {
                const tipo = document.getElementById('tipo-desc').value;
                if(tipo === 'porcentaje') {
                    const porc = parseFloat(document.getElementById('input-porcentaje-desc').value) || 0;
                    mntDesc = totalBruto * (porc / 100);
                    document.getElementById('input-monto-desc').value = mntDesc.toFixed(2);
                } else {
                    mntDesc = parseFloat(document.getElementById('input-monto-desc').value) || 0;
                    const porc = totalBruto > 0 ? (mntDesc / totalBruto) * 100 : 0;
                    document.getElementById('input-porcentaje-desc').value = porc.toFixed(0);
                }
            }

            const aplicaIva = document.getElementById('switch-iva').checked;
            const baseImponible = totalBruto - mntDesc;
            const mntIva = aplicaIva ? (baseImponible * 0.15) : 0;
            const granTotal = baseImponible + mntIva;

            document.getElementById('txt-iva').innerText = mntIva.toLocaleString('en-US', {minimumFractionDigits: 2});
            document.getElementById('txt-total').innerText = granTotal.toLocaleString('en-US', {minimumFractionDigits: 2});

            document.getElementById('val-iva').value = mntIva;
            document.getElementById('val-total').value = granTotal;
        }

        actualizarFirma();
        calcular();
    </script>
</body>
</html>
