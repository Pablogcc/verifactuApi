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
                'numFactura' => '7',
                'idVersion' => '1.0',
                'idInstalacion' => '_73J0TGQG2',
                'idEmisorFactura' => '29527583E',
                'numSerieFactura' => '05/0000001',
                'fechaExpedicionFactura' => '01-09-2025',
                'nombreEmisor' => 'INNOVA VILLAS WORLD SL',
                'cifEmisor' => 'B10689461',
                'tipoFactura' => 'F1',
                'fechaOperacion' => '21-07-2025',
                'descripcionOperacion' => 'Venta',
                'huellaAnterior' => 'F83A905FFFB74CD72BD91FF0BA5EBCF0400A9BF09D6D4C12C533707FD8ECD0EE',
                'nombreCliente' => 'INNOVA VILLAS WORLD SL',
                'nifCliente' => 'B10689461',
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
                //'IDEmisorFacturaAnterior' => '48456925L',
                //'numSerieFacturaAnterior' => '05/00000007',
                //'FechaExpedicionFacturaAnterior' => '07-07-2025',
                'huella' => 'F83A905FFFB74CD72BD91FF0BA5EBCF0400A9BF09D6D4C12C533707FD8ECD0EE',
                'fechaHoraHusoGenRegistro' => '2025-07-22T06:56:13+02:00',
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
            ],
            [
                'serie' => '05',
                'numFactura' => '7',
                'idVersion' => '1.0',
                'idInstalacion' => '_73J0TGQG2',
                'idEmisorFactura' => '48456925L',
                'numSerieFactura' => '05/0000002',
                'fechaExpedicionFactura' => '03-09-2025',
                'nombreEmisor' => 'INNOVA VILLAS WORLD SL',
                'cifEmisor' => 'B10689461',
                'tipoFactura' => 'F1',
                'fechaOperacion' => '21-07-2025',
                'descripcionOperacion' => 'Venta',
                'huellaAnterior' => 'F83A905FFFB74CD72BD91FF0BA5EBCF0400A9BF09D6D4C12C533707FD8ECD0EE',
                'nombreCliente' => 'INNOVA VILLAS WORLD SL',
                'nifCliente' => 'B10689461',
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
                //'IDEmisorFacturaAnterior' => '48456925L',
                //'numSerieFacturaAnterior' => '05/00000007',
                //'FechaExpedicionFacturaAnterior' => '07-07-2025',
                'huella' => 'F83A905FFFB74CD72BD91FF0BA5EBCF0400A9BF09D6D4C12C533707FD8ECD0EE',
                'fechaHoraHusoGenRegistro' => '2025-07-22T06:56:13+02:00',
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
