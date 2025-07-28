<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Emisores extends Model
{
    use HasFactory;

    public $table = 'emisores';

   public $fillable = [
        'cif',
        'certificado',
        'certificado_Key',
        'password',
        'fechaValidez'
    ];
}
