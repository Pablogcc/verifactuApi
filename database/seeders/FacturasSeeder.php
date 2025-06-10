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
            ['idVersion' => 'L15',
            'idEmisorFactura' => '12345678Z',
            'numSerieFactura' => 'F2024-0001',
            'fechaExpedicionFactura' => '20-02-2024',
            'refExterna' => 'REF123456',
            'nombreRazonEmisor' => 'Empresa S.A.',
            'subsanacion' => 'L4',
            'rechazoPrevio' => 'L17',
            'tipoFactura' => 'L2',
            'idEmisorFacturaRectificada' => '12345678X',
            'numSerieFacturaRectificada' => 'F2023-0009',
            'fechaExpedicionFacturaRectificada' => '15-12-2023',
            'idEmisorFacturaSustituida' => '87654321Y',
            'numSerieFacturaSustituida' => 'F2023-0010',
            'fechaExpedicionFacturaSustituida' => '10-11-2023',
            'baseRectificada' => 100.50,
            'cuotaRectificada' => 21.00,
            'cuotaRecargoRectificado' => 2.50,
            'fechaOperacion' => '21-02-2024',
            'descripcionOperacion' => 'Venta de productos',
            'facturaSimplificadaArt7273' => 'L4',
            'facturaSinIdentifDestinatarioArt61d' => 'L5',
            'macrodato' => 'L14',
            'emitidaPorTerceroODestinatario' => 'L6',
            'nombreRazon' => 'Cliente S.L.',
            'nif' => '87654321X',
            'codigoPais' => 'ES',
            'idType' => 'L7',
            'id' => 'ID12345',
            'cupon' => 'L4',
            'impuesto' => 'L1',
            'claveRegimen' => 'L8A',
            'calificacionOperacion' => 'L9',
            'operacionExenta' => 'L10',
            'tipoImpositivo' => 21.00,
            'baseImponibleOimporteNoSujeto' => 150.00,
            'baseImponibleACoste' => 140.00,
            'cuotaRepercutida' => 31.50,
            'tipoRecargoEquivalencia' => 5.20,
            'cuotaRecargoEquivalencia' => 7.80,
            'cuotaTotal' => 39.30,
            'importeTotal' => 189.30,
            'primerRegistro' => '1',
            'huella' => 'huella123456',
            'fechaHoraHusoGenRegistro' => '2024-02-20T19:20:30+01:00',
            'numRegistroAcuerdoFacturacion' => 'REG001234',
            'idAcuerdoSistemaInformatico' => 'SISTEMA5678',
            'tipoHuella' => 'L12',
        ],
        ['idVersion' => 'L15',
            'idEmisorFactura' => '12345678Z',
            'numSerieFactura' => 'F2024-0001',
            'fechaExpedicionFactura' => '20-02-2024',
            'refExterna' => 'REF123456',
            'nombreRazonEmisor' => 'Empresa S.A.',
            'subsanacion' => 'L4',
            'rechazoPrevio' => 'L17',
            'tipoFactura' => 'L2',
            'idEmisorFacturaRectificada' => '12345678X',
            'numSerieFacturaRectificada' => 'F2023-0009',
            'fechaExpedicionFacturaRectificada' => '15-12-2023',
            'idEmisorFacturaSustituida' => '87654321Y',
            'numSerieFacturaSustituida' => 'F2023-0010',
            'fechaExpedicionFacturaSustituida' => '10-11-2023',
            'baseRectificada' => 100.50,
            'cuotaRectificada' => 21.00,
            'cuotaRecargoRectificado' => 2.50,
            'fechaOperacion' => '21-02-2024',
            'descripcionOperacion' => 'Venta de productos',
            'facturaSimplificadaArt7273' => 'L4',
            'facturaSinIdentifDestinatarioArt61d' => 'L5',
            'macrodato' => 'L14',
            'emitidaPorTerceroODestinatario' => 'L6',
            'nombreRazon' => 'Cliente S.L.',
            'nif' => '87654321X',
            'codigoPais' => 'ES',
            'idType' => 'L7',
            'id' => 'ID12345',
            'cupon' => 'L4',
            'impuesto' => 'L1',
            'claveRegimen' => 'L8A',
            'calificacionOperacion' => 'L9',
            'operacionExenta' => 'L10',
            'tipoImpositivo' => 21.00,
            'baseImponibleOimporteNoSujeto' => 150.00,
            'baseImponibleACoste' => 140.00,
            'cuotaRepercutida' => 31.50,
            'tipoRecargoEquivalencia' => 5.20,
            'cuotaRecargoEquivalencia' => 7.80,
            'cuotaTotal' => 39.30,
            'importeTotal' => 189.30,
            'primerRegistro' => '1',
            'huella' => 'huella123456',
            'fechaHoraHusoGenRegistro' => '2024-02-20T19:20:30+01:00',
            'numRegistroAcuerdoFacturacion' => 'REG001234',
            'idAcuerdoSistemaInformatico' => 'SISTEMA5678',
            'tipoHuella' => 'L12',
            ]

        ]);
    }
}
