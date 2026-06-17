<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
  public function up(): void
{
    Schema::create('proforma_detalles', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('proforma_id');
        $table->string('descripcion')->nullable();
        $table->integer('cantidad')->default(1);
        $table->decimal('precio_unitario', 18, 2)->default(0);
        $table->decimal('subtotal', 18, 2)->default(0);
        $table->timestamps();

        // Relación que amarra el detalle a su proforma correspondiente
        $table->foreign('proforma_id')->references('id')->on('proformas')->onDelete('cascade');
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('proforma_detalles');
    }
};
