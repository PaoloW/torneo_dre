<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Solo lectura: el insert lo hace GrupoController dentro de la transacción.
 */
class PuestoAuditoria extends Model
{
    // la tabla real es 'puesto_auditoria' (sin pluralizar)
    protected $table = 'puesto_auditoria';

    public $timestamps = false;

    protected $guarded = [];
}
