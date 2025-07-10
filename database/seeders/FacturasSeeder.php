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
                'nombreEmisor' => 'ALBERTO BARBERA FERRANDEZ',
                'cifEmisor' => '48456925L',
                'tipoFactura' => 'F1',
                'fechaOperacion' => '2025-07-04T00:00:00.000',
                'descripcionOperacion' => 'Venta',
                'huellaAnterior' => '1B799E6F0A8F2AC9EFFE4D2519001CFDAF551FD2C71C8AEB49434A7BA58200DE',
                'nombreCliente' => 'ALBERTO BARBERA FERRANDEZ',
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
                'cuotaTotal' => 1.05,
                'importeTotal' => 6.05,
                'IDEmisorFacturaAnterior' => '48456925L',
                'numSerieFacturaAnterior' => '04/00000002',
                'FechaExpedicionFacturaFacturaAnterior' => '07-07-2025',
                'huella' => 'B78719F5FD0D4C3A89C3467D26E4385ABCF86F893CA7459BA8D808EC105C038A',
                'fechaHoraHusoGenRegistro' => '2025-07-09T10:34:04+02:00',
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
