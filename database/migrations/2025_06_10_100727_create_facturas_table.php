<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('facturas', function (Blueprint $table) {
            $table->id();

            $table->string('serie', 2);
            $table->string('numFactura', 8);

            $table->string('idVersion');

            //IDFactura
            $table->string('idEmisorFactura');
            $table->string('numSerieFactura');
            $table->string('fechaExpedicionFactura');

            //trigger de filtración
            $table->string('ejercicio');

            $table->string('refExterna')->default('');
            $table->string('nombreEmisor');
            $table->string('cifEmisor');
            $table->string('subsanacion')->nullable();
            $table->string('rechazoPrevio')->nullable();
            $table->string('tipoFactura')->default('F1');

            // Facturas Rectificadas
            $table->string('idEmisorFacturaRectificada')->nullable();
            $table->string('numSerieFacturaRectificada')->nullable();
            $table->string('fechaExpedicionFacturaRectificada')->nullable();

            // Facturas Sustituidas
            $table->string('idEmisorFacturaSustituida')->nullable();
            $table->string('numSerieFacturaSustituida')->nullable();
            $table->string('fechaExpedicionFacturaSustituida')->nullable();
            $table->double('baseRectificada')->nullable();
            $table->double('cuotaRectificada')->nullable();
            $table->double('cuotaRecargoRectificado')->nullable();
            $table->string('fechaOperacion');
            $table->string('descripcionOperacion');
            $table->string('facturaSimplificadaArt7273')->default('N');
            $table->string('facturaSinIdentifDestinatarioArt61d')->default('N');
            $table->string('macrodato')->default('N');
            $table->string('emitidaPorTerceroODestinatario')->default('N');

            //IDDestinatario
            $table->string('nombreCliente');
            $table->string('nifCliente');
            $table->string('codigoPais');
            $table->string('idType')->default('NIF');

            $table->string('idTypeNum')->default('01');

            // Datos fiscales
            $table->string('cupon')->default('N');
            $table->integer('impuesto')->default(01);

            //DetalleDesglose 1
            $table->string('claveRegimen', 2)->default('01');
            $table->string('calificacionOperacion', 2)->default('S1');
            $table->string('operacionExenta', 1)->default('N');
            $table->double('tipoImpositivo', 8, 2)->default(21);
            $table->double('tipoImpositivo2', 8, 2)->nullable();
            $table->double('tipoImpositivo3', 8, 2)->nullable();
            $table->double('tipoImpositivo4', 8, 2)->nullable();
            $table->double('baseImponibleOimporteNoSujeto')->default(13);
            $table->double('baseImponibleACoste', 8, 2);
            $table->double('baseImponibleACoste2', 8, 2)->nullable();
            $table->double('baseImponibleACoste3', 8, 2)->nullable();
            $table->double('baseImponibleACoste4', 8, 2)->nullable();
            $table->double('cuotaRepercutida')->default(0.4);
            $table->double('cuotaRepercutida2', 8, 2)->nullable();
            $table->double('cuotaRepercutida3', 8, 2)->nullable();
            $table->double('cuotaRepercutida4', 8, 2)->nullable();
            $table->double('tipoRecargoEquivalencia', 8, 2);
            $table->double('tipoRecargoEquivalencia2', 8, 2)->nullable();
            $table->double('tipoRecargoEquivalencia3', 8, 2)->nullable();
            $table->double('tipoRecargoEquivalencia4', 8, 2)->nullable();
            $table->double('cuotaRecargoEquivalencia', 8, 2);
            $table->double('cuotaRecargoEquivalencia2', 8, 2)->nullable();
            $table->double('cuotaRecargoEquivalencia3', 8, 2)->nullable();
            $table->double('cuotaRecargoEquivalencia4', 8, 2)->nullable();

            /*DetalleDesglose2(A PARTE)
            $table->string('claveRegimenSegundo')->default('01');
            $table->string('calificacionOperacionSegundo')->default('S1');
            $table->double('tipoImpositivoSegundo')->default(21);
            $table->double('baseImponibleOimporteNoSujetosegundo')->default(100);
            $table->double('cuotaRepercutidaSegundo')->default(21);*/

            $table->double('cuotaTotal', 8);
            $table->double('importeTotal', 8);
            $table->string('primerRegistro')->nullable();

            //RegistroAnterior(A PARTE)
            $table->string('IDEmisorFacturaAnterior')->nullable();
            $table->string('numSerieFacturaAnterior')->nullable();
            $table->string('FechaExpedicionFacturaAnterior')->nullable();

            $table->string('huellaAnterior')->nullable();

            // Registro adicional
            $table->string('fechaHoraHusoGenRegistro');
            $table->string('numRegistroAcuerdoFacturacion')->nullable();
            $table->string('idAcuerdoSistemaInformatico')->nullable();
            $table->string('tipoHuella')->default('01');
            $table->string('huella');

            //SistemaInformatico
            $table->string('nombreFabricanteSoftware')->default('SAUBER OFIMATICA S.L.');
            $table->string('nifFabricanteSoftware')->default('B53816435');
            $table->string('nombreSoftware');
            $table->string('identificadorSoftware')->default('77');
            $table->string('versionSoftware');
            $table->string('numeroInstalacion')->default('383');
            $table->string('tipoUsoPosibleVerifactu')->default('S');
            $table->string('tipoUsoPosibleMultiOT')->default('S');
            $table->string('indicadorMultiplesOT')->default('S');

            //$table->string('enviados')->default('pendiente');
            $table->text('error')->nullable();
            $table->integer('estado_proceso')->default(0);
            //$table->string('estado_proceso')->default('desbloqueada');
            $table->integer('estado_registro')->default(0);

            //Campos para la factura electrónica
            $table->string('oficontable')->nullable();
            $table->string('orggestor')->nullable();
            $table->string('utramitadora')->nullable();
            $table->text('notas')->nullable();
            $table->string('inicioperiodo')->nullable();
            $table->string('finperiodo')->nullable();

            //Datos dirección del emisor
            $table->string('emisor_direc', 100);
            $table->string('emisor_cpostal', 10);
            $table->string('emisor_ciudad', 50);
            $table->string('emisor_prov', 50);
            $table->string('emisor_cpais');

            //Datos dirección del receptor
            $table->string('receptor_direc', 100);
            $table->string('receptor_cpostal', 10);
            $table->string('receptor_ciudad', 50);
            $table->string('receptor_prov', 50);
            $table->string('receptor_cpais', 3);

            //Datos dirección del oficontable
            $table->string('oficontable_direc', 100)->nullable();
            $table->string('oficontable_cpostal', 10)->nullable();
            $table->string('oficontable_ciudad', 50)->nullable();
            $table->string('oficontable_prov', 50)->nullable();
            $table->string('oficontable_cpais', 3)->nullable();

            //Datos dirección del orggestor
            $table->string('orggestor_direc', 100)->nullable();
            $table->string('orggestor_cpostal', 10)->nullable();
            $table->string('orggestor_ciudad', 50)->nullable();
            $table->string('orggestor_prov', 50)->nullable();
            $table->string('orggestor_cpais', 3)->nullable();

            //Datos dirección del utramitadora
            $table->string('utramitadora_direc', 100)->nullable();
            $table->string('utramitadora_cpostal', 10)->nullable();
            $table->string('utramitadora_ciudad', 50)->nullable();
            $table->string('utramitadora_prov', 50)->nullable();
            $table->string('utramitadora_cpais', 3)->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('facturas');
    }
};
