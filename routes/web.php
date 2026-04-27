<?php

use Illuminate\Support\Facades\Route;
// 1. Importamos tu controlador
use App\Http\Controllers\CalculadoraController;

Route::get('/', function () {
    return view('welcome');
});

// 2. Creamos la ruta para el PDF
// Debe ser POST porque enviaremos los datos del formulario
Route::post('/generar-pdf', [CalculadoraController::class, 'generarPDF'])->name('pdf.generar');