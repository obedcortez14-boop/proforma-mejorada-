<?php

use Illuminate\Support\Facades\Route;
// 1. Importamos tu controlador
use App\Http\Controllers\CalculadoraController;

Route::get('/', function () {
    return view('welcome');
});

// 2. Ruta para el PDF (Ya la tenías, la mantenemos igual)
Route::post('/generar-pdf', [CalculadoraController::class, 'generarPDF'])->name('pdf.generar');

// =====================================================
// 3. NUEVAS RUTAS: HISTORIAL Y EDICIÓN DE PROFORMAS
// =====================================================

// Esta es la ruta exacta que te estaba pidiendo el controlador (Historial)
Route::get('/proformas', [CalculadoraController::class, 'index'])->name('proformas.index');

// Rutas necesarias para cargar el formulario de edición y procesar la actualización
Route::get('/proformas/{id}/edit', [CalculadoraController::class, 'edit'])->name('proformas.edit');
Route::put('/proformas/{id}', [CalculadoraController::class, 'update'])->name('proformas.update');
