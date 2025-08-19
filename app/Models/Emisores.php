<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Emisores extends Model
{
    protected $table = 'emisores';
    protected $primaryKey = 'cif';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'cif',
        'certificado',
        'password',
        'correoAdministrativo',
        'nombreEmpresa',
        'fechaValidez'
    ];
}
