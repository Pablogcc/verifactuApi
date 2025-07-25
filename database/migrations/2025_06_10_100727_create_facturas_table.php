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
            $table->string('idInstalacion');

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
            $table->string('impuesto')->default('IVA');

            //DetalleDesglose 1
            $table->string('claveRegimen')->default('01');
            $table->string('calificacionOperacion')->default('S1');
            $table->string('operacionExenta')->default('N');
            $table->double('tipoImpositivo')->default(21);
            $table->double('tipoImpositivo2')->nullable();
            $table->double('tipoImpositivo3')->nullable();
            $table->double('tipoImpositivo4')->nullable();
            $table->double('baseImponibleOimporteNoSujeto')->default(13);
            $table->double('baseImponibleACoste');
            $table->double('baseImponibleACoste2')->nullable();
            $table->double('baseImponibleACoste3')->nullable();
            $table->double('baseImponibleACoste4')->nullable();
            $table->double('cuotaRepercutida')->default(0.4);
            $table->double('cuotaRepercutida2')->nullable();
            $table->double('cuotaRepercutida3')->nullable();
            $table->double('cuotaRepercutida4')->nullable();
            $table->double('tipoRecargoEquivalencia');
            $table->double('tipoRecargoEquivalencia2')->nullable();
            $table->double('tipoRecargoEquivalencia3')->nullable();
            $table->double('tipoRecargoEquivalencia4')->nullable();
            $table->double('cuotaRecargoEquivalencia');
            $table->double('cuotaRecargoEquivalencia2')->nullable();
            $table->double('cuotaRecargoEquivalencia3')->nullable();
            $table->double('cuotaRecargoEquivalencia4')->nullable();

            /*DetalleDesglose2(A PARTE)
            $table->string('claveRegimenSegundo')->default('01');
            $table->string('calificacionOperacionSegundo')->default('S1');
            $table->double('tipoImpositivoSegundo')->default(21);
            $table->double('baseImponibleOimporteNoSujetosegundo')->default(100);
            $table->double('cuotaRepercutidaSegundo')->default(21);*/

            $table->double('cuotaTotal');
            $table->double('importeTotal');
            $table->string('primerRegistro')->nullable();

            //RegistroAnterior(A PARTE)
            $table->string('IDEmisorFacturaAnterior')->nullable();
            $table->string('numSerieFacturaAnterior')->nullable();
            $table->string('FechaExpedicionFacturaAnterior')->nullable();

            $table->string('huellaAnterior');

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
