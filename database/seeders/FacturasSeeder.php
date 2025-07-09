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
                'idVersion' => '1.0',
                'idInstalacion' => '_73J0TGQG2',
                'idEmisorFactura' => '48456925L',
                'numSerieFactura' => '04-00000010',
                'fechaExpedicionFactura' => '04-07-2025',
                'nombreEmisor' => 'BARBERA FERRANDEZ ALBERTO',
                'cifEmisor' => '48456925L',
                'tipoFactura' => 'F1',
                'fechaOperacion' => '2025-07-04T00:00:00.000',
                'descripcionOperacion' => 'Venta',
                'huellaAnterior' => 'DAC96938A6BC89FAB17103229F743A16F071097715EC18367D',
                'nombreCliente' => 'BARBERA FERRANDEZ ALBERTO',
                'nifCliente' => '48456925L',
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
                'cuotaTotal' => 3,
                'importeTotal' => 28,
                'IDEmisorFacturaAnterior' => '48456925L',
                'numSerieFacturaAnterior' => '04/00000002',
                'FechaExpedicionFacturaFacturaAnterior' => '07-07-2025',
                'huella' => '586C7AD27DAA8AD05D0BA8200B94E1220F4BFE9D703C918388',
                'fechaHoraHusoGenRegistro' => '2025-07-04T13:32:16.819',
                'tipoHuella' => '01',
                'nombreFabricanteSoftware' => 'SAUBER OFIMATICA S.L.',
                'nifFabricanteSoftware' => 'B53816435',
                'nombreSoftware' => 'Euromanager',
                'identificadorSoftware' => '77',
                'versionSoftware' => '2.0.3',
                'numeroInstalacion' => '383',
                'estado_proceso' => 'desbloqueada',
                'created_at' => now(),
                'updated_at' => now()
            ]           
        ]);
    }
}
