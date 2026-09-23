<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CalculadoraController;

// La pantalla principal obtiene el contador a través de ContadorProformaService
// (el controlador maneja el error de conexión y lo entrega a la vista).
Route::get('/', [CalculadoraController::class, 'inicio']);

Route::post('/generar-pdf', [CalculadoraController::class, 'generarPDF'])->name('pdf.generar');

// =====================================================
// HISTORIAL Y EDICIÓN DE PROFORMAS
// =====================================================

Route::get('/proformas', [CalculadoraController::class, 'index'])->name('proformas.index');

// Asegúrate de escribir "PorId" con I mayúscula y d minúscula:
Route::get('/proformas/{id}/pdf', [CalculadoraController::class, 'generarPDFPorId'])->name('proformas.pdf');

Route::get('/proformas/{id}/edit', [CalculadoraController::class, 'edit'])->name('proformas.edit');
Route::put('/proformas/{id}', [CalculadoraController::class, 'update'])->name('proformas.update');