<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class Facturas2Seeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('facturas')->insert([
            [
                'serie' => '07',
                'numFactura' => '2',
                'idVersion' => '1.0',
                'idEmisorFactura' => '48456925L',
                'numSerieFactura' => '07/0000002',
                'fechaExpedicionFactura' => '24-07-2025',
                'nombreEmisor' => 'ALBERTO BARBERA FERRANDEZ',
                'cifEmisor' => '48456925L',
                'tipoFactura' => 'F1',
                'fechaOperacion' => '24-07-2025',
                'descripcionOperacion' => 'Venta',
                //'huellaAnterior' => 'FFF480A390C36E87746E645FCE6DF4161982FF753E859AFA4643CB4E65B018E2',
                'nombreCliente' => 'LIMPIEZAS MAMEGA',
                //NIF intracomunitario: DE210045333
                'nifCliente' => 'DE210045333',
                'codigoPais' => 'DE',
                'idType' => 'NIF',
                'idTypeNum' => '02',
                'cupon' => 'N',
                'impuesto' => 'IVA',
                'claveRegimen' => '01',
                'calificacionOperacion' => 'S1',
                'operacionExenta' => 'N',
                'tipoImpositivo' => 21,
                'baseImponibleACoste' => 50.5,
                'cuotaRepercutida' => 10.5,
                'tipoRecargoEquivalencia' => 5,
                'cuotaRecargoEquivalencia' => 0,
                'cuotaTotal' => 10.3,
                'importeTotal' => 60.5,
                //'IDEmisorFacturaAnterior' => '48456925L',
                //'numSerieFacturaAnterior' => '07/00000004',
                //'FechaExpedicionFacturaAnterior' => '24-07-2025',
                'huella' => '33AEEC700D82618E396295CB6DED3076ED99DB313483A9370B6D9740BD643944',
                'fechaHoraHusoGenRegistro' => '2025-07-31T10:00:31+02:00',
                'tipoHuella' => '01',
                'nombreFabricanteSoftware' => 'SAUBER OFIMATICA S.L.',
                'nifFabricanteSoftware' => 'B53816435',
                'nombreSoftware' => 'Euromanager',
                'identificadorSoftware' => '77',
                'versionSoftware' => '2.1.4',
                'numeroInstalacion' => '383',
                'estado_registro' => 1,
                'created_at' => now(),
                'updated_at' => now()
            ],
        ]);
    }
}
