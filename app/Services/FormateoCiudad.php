<?php

namespace App\Services;

class FormateoCiudad
{

    public function normalizar(string $name): string {
        //Eliminamos los espacios en blanco al inicio y al final del texto
        $name = trim($name);

        //Si tiene coma, lo reordenamos
        if (strpos($name, ',') !== false) {
            [$parte1, $parte2] = array_map('trim', explode(',', $name, 2));
            $name = $parte2 . ' ' . $parte1;
        }

        //Reemplaza los espacios múltiples por un solo espacio
        $name = preg_replace('/\s+/', ' ', $name);
        //Ponemos todo el texto en mayúscula
        $name = mb_strtoupper($name, 'UTF-8');

        return $name;
    }
}
