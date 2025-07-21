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
        // 'numSerieFactura' => 'F2025-' . str_pad($i, 4, '0', STR_PAD_LEFT),
        $coste = 140.00 + $i;
        $importe = 189.30 + $i;
        $total = 39.30 + $i;

        $data = [
            'serie' => '05',
            'numFactura' => '2',
            'idVersion' => '1.0',
            'idInstalacion' => '_73J0TGQG2',
            'idEmisorFactura' => '48456925L',
            'numSerieFactura' => '05/00000002',
            'fechaExpedicionFactura' => '21-07-2025',
            'nombreEmisor' => 'ALBERTO BARBERA FERRANDEZ',
            'cifEmisor' => '48456925L',
            'tipoFactura' => 'F1',
            'fechaOperacion' => '21-07-2025',//'2025-07-04T00:00:00.000',
            'descripcionOperacion' => 'Venta',
            'huellaAnterior' => 'BEDEA7C9A07BF49374203F2858C37B72D406391CE2AEECA497D110501C5D6FE6',
            'nombreCliente' => 'RENAULT TRUCKS SL',
            'nifCliente' => 'B5484544',
            'codigoPais' => 'ES',
            'idType' => 'NIF',
            'cupon' => 'N',
            'impuesto' => 'IVA',
            'claveRegimen' => '01',
            'calificacionOperacion' => 'S1',
            'operacionExenta' => 'N',
            'tipoImpositivo' => 21,
            'baseImponibleACoste' => 20,
            'cuotaRepercutida' => 1.05,
            'tipoRecargoEquivalencia' => 5.20,
            'cuotaRecargoEquivalencia' => 7.80,
            'cuotaTotal' => 1.05,
            'importeTotal' => 6.05,
            'IDEmisorFacturaAnterior' => '48456925L',
            'numSerieFacturaAnterior' => '04/00000002',
            'FechaExpedicionFacturaFacturaAnterior' => '07-07-2025',
            'huella' => 'F40CC3196C0A4098B72E394A4832E2DB02D1AE81BC67D9A8700FEF7D82CBC224',
            'fechaHoraHusoGenRegistro' => '2025-07-21T06:36:24+02:00',
            'tipoHuella' => '01',
            'nombreFabricanteSoftware' => 'SAUBER OFIMATICA S.L.',
            'nifFabricanteSoftware' => 'B53816435',
            'nombreSoftware' => 'Euromanager',
            'identificadorSoftware' => '77',
            'versionSoftware' => '2.0.3',
            'numeroInstalacion' => '383',
            'estado_proceso' => 0,
            'created_at' => now(),
            'updated_at' => now()
        ];

        $i++;
        return $data;
    }
}
