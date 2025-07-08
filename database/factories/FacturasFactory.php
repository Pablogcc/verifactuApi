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

        $coste = 140.00 + $i;
        $importe = 189.30 + $i;
        $total = 39.30 + $i;

        $data = [
            'idVersion' => '1.0',
            'idInstalacion' => '_73J0TGQG2',
            'idEmisorFactura' => '48456925L',
            'numSerieFactura' => 'F2025-' . str_pad($i, 4, '0', STR_PAD_LEFT),
            'fechaExpedicionFactura' => now()->format('d-m-Y'),
            'refExterna' => 'REF' . $i,
            'nombreEmisor' => 'Empresa S.A. ' . $i,
            'cifEmisor' => '48456925L',
            'subsanacion' => 'L4',
            'rechazoPrevio' => 'L17',
            'tipoFactura' => 'F1',
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
            'facturaSimplificadaArt7273' => 'N',
            'facturaSinIdentifDestinatarioArt61d' => 'N',
            'macrodato' => 'N',
            'emitidaPorTerceroODestinatario' => 'N',
            'nombreCliente' => 'ALBERTO FERRANDEZ BARBERA',
            'nifCliente' => '48456925L',
            'codigoPais' => 'ES',
            'idType' => 'NIF',
            'cupon' => 'N',
            'impuesto' => 'IVA',
            'claveRegimen' => '01',
            'calificacionOperacion' => 'S1',
            'operacionExenta' => 'N',
            'tipoImpositivo' => 21.00,
            'baseImponibleOimporteNoSujeto' => 13,
            'baseImponibleACoste' => $coste,
            'cuotaRepercutida' => 0.4,
            'tipoRecargoEquivalencia' => 5.20,
            'cuotaRecargoEquivalencia' => 7.80,
            'cuotaTotal' => $total,
            'importeTotal' => $importe,
            'primerRegistro' => '1',
            'IDEmisorFacturaAnterior' => '48456925L',
            'numSerieFacturaAnterior' => 'F2025-' . str_pad($i, 4, '0', STR_PAD_LEFT),
            'FechaExpedicionFacturaFacturaAnterior' => now()->format('d-m-Y'),
            'huellaAnterior' => 'DBEB378BD54F555E9F6F0380C5C2F3DF69565454A905F75CF74D4D4058367B90',
            'fechaHoraHusoGenRegistro' => now()->format('Y-m-d\TH:i:sP'),
            'numRegistroAcuerdoFacturacion' => 'REG' . str_pad($i, 6, '0', STR_PAD_LEFT),
            'idAcuerdoSistemaInformatico' => 'SISTEMA' . $i,
            'tipoHuella' => '01',
            'huella' => '31C75CBA131C5EB4AEEB646CCB20BA4E6AA24670CEC519B3FA38DAAC4AD084D9',
            'nombreFabricanteSoftware' => 'Sauber Ofimática S.L.',
            'nifFabricanteSoftware' => '48456925L',
            'nombreSoftware' => 'EUROMANAGER',
            'identificadorSoftware' => '77',
            'versionSoftware' => '1.0',
            'numeroInstalacion' => '383',
            'tipoUsoPosibleVerifactu' => 'S',
            'tipoUsoPosibleMultiOT' => 'S',
            'tipoUsoPosibleMultiOT' => 'S',
            'identificadorSoftware' => 'sauber-facturas-001',
            'created_at' => now(),
            'updated_at' => now(),
        ];

        $i++;
        return $data;
    }
}
