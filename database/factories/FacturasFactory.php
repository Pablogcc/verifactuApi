<?php

namespace Database\Factories;

use App\Models\Facturas;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Facturas>
 */
class FacturasFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        static $i = 1;
        $letras = 'TRWAGMYFPDXBNJZSQVHLCKE';
        $num = str_pad($i, 8, '0', STR_PAD_LEFT);
        $letra = $letras[intval($num) % 23];
        $dni = $num . $letra;

        $base = 150.00 + $i;
        $coste = 140.00 + $i;
        $cuota = 31.50 + $i;
        $importe = 189.30 + $i;
        $total = 39.30 + $i;

        $data = [
            'idVersion' => '1.0',
            'idEmisorFactura' => 'EMISOR' . $i,
            'numSerieFactura' => 'F2025-' . str_pad($i, 4, '0', STR_PAD_LEFT),
            'fechaExpedicionFactura' => now()->format('d-m-Y'),
            'refExterna' => 'REF' . $i,
            'nombreRazonEmisor' => 'Empresa S.A. ' . $i,
            'subsanacion' => 'L4',
            'rechazoPrevio' => 'L17',
            'tipoFactura' => 'L2',
            'idEmisorFacturaRectificada' => 'RECT' . $i,
            'numSerieFacturaRectificada' => 'FR2024-' . str_pad($i, 4, '0', STR_PAD_LEFT),
            'fechaExpedicionFacturaRectificada' => now()->subDays(10)->format('d-m-Y'),
            'idEmisorFacturaSustituida' => 'SUST' . $i,
            'numSerieFacturaSustituida' => 'FS2024-' . str_pad($i, 4, '0', STR_PAD_LEFT),
            'fechaExpedicionFacturaSustituida' => now()->subDays(20)->format('d-m-Y'),
            'baseRectificada' => fake()->randomFloat(2, 100, 999),
            'cuotaRectificada' => fake()->randomFloat(2, 10, 99),
            'cuotaRecargoRectificado' => fake()->randomFloat(2, 1, 9),
            'fechaOperacion' => now()->format('d-m-Y'),
            'descripcionOperacion' => 'Venta de productos ' . $i,
            'facturaSimplificadaArt7273' => 'L4',
            'facturaSinIdentifDestinatarioArt61d' => 'L5',
            'macrodato' => 'L14',
            'emitidaPorTerceroODestinatario' => 'L6',
            'nombre' => 'Cliente S.L. ' . $i,
            'nif' => $dni,
            'codigoPais' => 'ES',
            'idType' => 'L7',
            'id' => (string)$i,
            'cupon' => 'L4',
            'impuesto' => 'L1',
            'claveRegimen' => 'L8A',
            'calificacionOperacion' => 'L9',
            'operacionExenta' => 'L10',
            'tipoImpositivo' => 21.00,
            'baseImponibleOimporteNoSujeto' => $base,
            'baseImponibleACoste' => $coste,
            'cuotaRepercutida' => $cuota,
            'tipoRecargoEquivalencia' => 5.20,
            'cuotaRecargoEquivalencia' => 7.80,
            'cuotaTotal' => $total,
            'importeTotal' => $importe,
            'primerRegistro' => '1',
            'huella' => 'huella' . $i,
            'fechaHoraHusoGenRegistro' => now()->format('Y-m-d\TH:i:sP'),
            'numRegistroAcuerdoFacturacion' => 'REG' . str_pad($i, 6, '0', STR_PAD_LEFT),
            'idAcuerdoSistemaInformatico' => 'SISTEMA' . $i,
            'tipoHuella' => 'L12',
            'created_at' => now(),
            'updated_at' => now(),
        ];

        $i++;
        return $data;
    }
}
