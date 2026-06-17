<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Proforma extends Model
{
    // Si tu tabla en SQL Server se llama exactamente 'proformas', Laravel la encuentra sola.
    // Por si acaso, con esta línea aseguramos el nombre exacto de la tabla:
    protected $table = 'proformas';

    // Esto le permite a Laravel guardar todos los campos que mandes desde el formulario de golpe
    protected $guarded = [];

    /**
     * Relación: Una proforma tiene muchos detalles.
     */
    public function detalles(): HasMany
    {
        // Vincula este modelo con el de los detalles usando la llave foránea 'proforma_id'
        return $this->hasMany(ProformaDetalle::class, 'proforma_id');
    }
}