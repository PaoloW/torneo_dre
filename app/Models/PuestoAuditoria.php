<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Solo lectura: el insert lo hace GrupoController dentro de la transacción.
 */
class PuestoAuditoria extends Model
{
    public $timestamps = false;

    protected $guarded = [];
}
