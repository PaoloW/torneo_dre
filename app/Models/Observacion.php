<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Observacion extends Model
{
    // pluralización irregular: la tabla es 'observaciones'
    protected $table = 'observaciones';

    protected $guarded = [];
}
