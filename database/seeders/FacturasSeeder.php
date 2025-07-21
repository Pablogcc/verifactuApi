<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Str;
use Illuminate\Support\Facades\Hash;

class FacturasSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {

        DB::table('facturas')->insert([
            [
                'serie' => '05',
                'numFactura' => '5',
                'idVersion' => '1.0',
                'idInstalacion' => '_73J0TGQG2',
                'idEmisorFactura' => '48456925L',
                'numSerieFactura' => '05/00000006',
                'fechaExpedicionFactura' => '21-07-2025',
                'nombreEmisor' => 'ALBERTO BARBERA FERRANDEZ',
                'cifEmisor' => '48456925L',
                'tipoFactura' => 'F1',
                'fechaOperacion' => '21-07-2025', //'2025-07-04T00:00:00.000',
                'descripcionOperacion' => 'Venta',
                'huellaAnterior' => '31B0496DE9C8F60B67F850CBC9F4EAB3F4950CA88880B478E23E1E1DB45FD2BD',
                'nombreCliente' => 'RENAULT TRUCKS SL',
                'nifCliente' => 'B82892563',
                'codigoPais' => 'ES',
                'idType' => 'NIF',
                'cupon' => 'N',
                'impuesto' => 'IVA',
                'claveRegimen' => '01',
                'calificacionOperacion' => 'S1',
                'operacionExenta' => 'N',
                'tipoImpositivo' => 21,
                'baseImponibleACoste' => 20,
                'cuotaRepercutida' => 0.4,
                'tipoRecargoEquivalencia' => 5.20,
                'cuotaRecargoEquivalencia' => 7.80,
                'cuotaTotal' => 1.05,
                'importeTotal' => 6.05,
                'IDEmisorFacturaAnterior' => '48456925L',
                'numSerieFacturaAnterior' => '04/00000002',
                'FechaExpedicionFacturaFacturaAnterior' => '07-07-2025',
                'huella' => '1F3F04261DC3C5DB6AE3C7FDD262ABB157212E8C23FA0D91181E6651FDE9CBD9',
                'fechaHoraHusoGenRegistro' => '2025-07-21T14:21:23+02:00',
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
            ]
        ]);
    }
}
