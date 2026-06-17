<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
   public function up(): void
{
    Schema::create('proformas', function (Blueprint $table) {
        $table->id();
        $table->string('codigo_proforma')->nullable();
        $table->string('cliente')->nullable();
        $table->date('fecha_emision')->nullable();
        $table->decimal('subtotal', 18, 2)->default(0);
        $table->decimal('impuesto', 18, 2)->default(0);
        $table->decimal('total', 18, 2)->default(0);
        $table->text('observaciones')->nullable();
        $table->string('estado')->default('Borrador');
        $table->timestamps();
    });
}

    public function down(): void
    {
        Schema::dropIfExists('proformas');
    }
};