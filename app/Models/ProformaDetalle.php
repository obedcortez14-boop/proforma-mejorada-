<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProformaDetalle extends Model
{
    // Le indicamos a Laravel el nombre exacto de tu tabla de detalles en SQL Server
    protected $table = 'proforma_detalles';

    // Permitimos la inserción masiva de datos en las filas
    protected $guarded = [];

    /**
     * Relación: Este detalle pertenece a una proforma madre.
     */
    public function proforma(): BelongsTo
    {
        return $this->belongsTo(Proforma::class, 'proforma_id');
    }
}
