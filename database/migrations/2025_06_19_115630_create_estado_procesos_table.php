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
        Schema::create('estado_procesos', function (Blueprint $table) {
            $table->id();
            $table->string('idVersion');
            $table->string('idInstalacion');
            $table->string('idEmisorFactura');
            $table->string('numSerieFactura');
            $table->string('fechaExpedicionFactura');
            $table->string('refExterna');
            $table->string('nombreEmisor');
            $table->string('cifEmisor');
            $table->string('subsanacion');
            $table->string('rechazoPrevio');
            $table->string('tipoFactura')->default('F1');
            // Facturas Rectificadas
            $table->string('idEmisorFacturaRectificada');
            $table->string('numSerieFacturaRectificada');
            $table->string('fechaExpedicionFacturaRectificada');
            // Facturas Sustituidas
            $table->string('idEmisorFacturaSustituida');
            $table->string('numSerieFacturaSustituida');
            $table->string('fechaExpedicionFacturaSustituida');
            $table->double('baseRectificada');
            $table->double('cuotaRectificada');
            $table->double('cuotaRecargoRectificado');
            $table->string('fechaOperacion');
            $table->string('descripcionOperacion');
            $table->string('facturaSimplificadaArt7273')->default('N');
            $table->string('facturaSinIdentifDestinatarioArt61d')->default('N');
            $table->string('macrodato')->default('N');
            $table->string('emitidaPorTerceroODestinatario')->default('N');
            $table->string('huellaAnterior');
            // Datos del destinatario
            $table->string('nombreCliente');
            $table->string('nifCliente');
            $table->string('codigoPais');
            $table->string('idType')->default('NIF');
            // Datos fiscales
            $table->string('cupon');
            $table->string('impuesto')->default('IVA');
            $table->string('claveRegimen');
            $table->string('calificacionOperacion');
            $table->string('operacionExenta')->default('N');
            $table->double('tipoImpositivo');
            $table->double('tipoImpositivo2')->nullable();
            $table->double('tipoImpositivo3')->nullable();
            $table->double('tipoImpositivo4')->nullable();
            $table->double('baseImponibleOimporteNoSujeto');
            $table->double('baseImponibleACoste');
            $table->double('baseImponibleACoste2')->nullable();
            $table->double('baseImponibleACoste3')->nullable();
            $table->double('baseImponibleACoste4')->nullable();
            $table->double('cuotaRepercutida');
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
            $table->double('cuotaTotal');
            $table->double('importeTotal');
            $table->string('primerRegistro');
            // Registro adicional
            $table->string('fechaHoraHusoGenRegistro');
            $table->string('numRegistroAcuerdoFacturacion');
            $table->string('idAcuerdoSistemaInformatico');
            $table->string('tipoHuella')->default('01');
            $table->string('huella');
            //Identificación nuestra empresa
            $table->string('nombreFabricanteSoftware')->default('SAUBER OFIMATICA S.L.');
            $table->string('nifFabricanteSoftware')->default('B53816435');
            $table->string('nombreSoftware');
            $table->string('identificadorSoftware');
            $table->string('versionSoftware');
            $table->string('numeroInstalacion');
            $table->string('tipoUsoPosibleVerifactu')->default('S');
            $table->string('tipoUsoPosibleMultiOT')->default('S');
            $table->string('indicadorMultiplesOT')->default('S');

            $table->string('enviados')->default('pendiente');
            $table->text('error')->nullable();
            $table->string('estado_proceso')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('estado_procesos');
    }
};
