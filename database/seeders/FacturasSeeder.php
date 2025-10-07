<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;


class FacturasSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {

        DB::table('facturas')->insert([
            [
                'serie' => 'HE',
                'numFactura' => '15',
                'idVersion' => '1.0',
                'idEmisorFactura' => 'B53816435',
                'numSerieFactura' => 'HE/0000014',
                'fechaExpedicionFactura' => '24-07-2025',
                'nombreEmisor' => 'ALBERTO BARBERA FERRANDEZ',
                'cifEmisor' => 'B53816435',
                'tipoFactura' => 'F1',
                'fechaOperacion' => '24-07-2025',
                'descripcionOperacion' => 'Venta',
                //'huellaAnterior' => 'FFF480A390C36E87746E645FCE6DF4161982FF753E859AFA4643CB4E65B018E2',
                'nombreCliente' => 'Alberto Barbera Ferrandez',
                'nifCliente' => 'B53816435',
                'codigoPais' => 'ESP',
                'idType' => 'NIF',
                'idTypeNum' => '01',
                'cupon' => 'N',
                'claveRegimen' => '01',
                'calificacionOperacion' => 'S1',
                'operacionExenta' => 'N',
                'tipoImpositivo' => 21,
                'baseImponibleACoste' => 50.5,
                'cuotaRepercutida' => 10.5,
                'tipoRecargoEquivalencia' => 5,
                'cuotaRecargoEquivalencia' => 0,
                'cuotaTotal' => 10.1,
                'importeTotal' => 60.5,
                //'IDEmisorFacturaAnterior' => '48456925L',
                //'numSerieFacturaAnterior' => '07/00000002',
                //'FechaExpedicionFacturaAnterior' => '24-07-2025',
                'idEmisorFacturaRectificada' => '48456925L',
                'numSerieFacturaRectificada' => '02/0000001',
                'fechaExpedicionFacturaRectificada' => '22-05-2025',
                'huella' => '33AEEC700D82618E396295CB6DED3076ED99DB313483A9370B6D9740BD643944',
                'fechaHoraHusoGenRegistro' => '2025-07-31T10:00:31+02:00',
                'tipoHuella' => '01',
                'nombreFabricanteSoftware' => 'SAUBER OFIMATICA S.L.',
                'nifFabricanteSoftware' => 'B53816435',
                'nombreSoftware' => 'Euromanager',
                'identificadorSoftware' => '77',
                'versionSoftware' => '2.1.4',
                'numeroInstalacion' => '383',
                'estado_proceso' => 0,
                'estado_registro' => 0,
                'emisor_direc' => 'Calle Aragón',
                'emisor_cpostal' => '03300',
                'emisor_ciudad' => 'Orihuela',
                'emisor_prov' => 'Alicante',
                'emisor_cpais' => 'ESP',
                'receptor_direc' => 'Calle Jose Antonio',
                'receptor_cpostal' => '03300',
                'receptor_ciudad' => 'Orihuela',
                'receptor_prov' => 'Alicante',
                'receptor_cpais' => 'ESP',
                //'oficontable' => '213asfawf214',
                //'orggestor' => 'WEQ QEW  ET RT E',
                //'utramitadora' => '-__4353634663463463436434',
                'created_at' => now(),
                'updated_at' => now()
            ]
        ]);
    }
}
