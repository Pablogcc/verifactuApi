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
            $table->string('tipoFactura');
            // Facturas Rectificadas
            $table->string('idEmisorFacturaRectificada');
            $table->string('numSerieFacturaRectificada');
            $table->string('fechaExpedicionFacturaRectificada');
            // Facturas Sustituidas
            $table->string('idEmisorFacturaSustituida');
            $table->string('numSerieFacturaSustituida');
            $table->string('fechaExpedicionFacturaSustituida');
            $table->decimal('baseRectificada', 10, 2);
            $table->decimal('cuotaRectificada', 10, 2);
            $table->decimal('cuotaRecargoRectificado', 10, 2);
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
            $table->decimal('tipoImpositivo', 10, 2);
            $table->decimal('tipoImpositivo2', 10, 2)->nullable();
            $table->decimal('tipoImpositivo3', 10, 2)->nullable();
            $table->decimal('tipoImpositivo4', 10, 2)->nullable();
            $table->decimal('baseImponibleOimporteNoSujeto', 10, 2);
            $table->decimal('baseImponibleACoste', 10, 2);
            $table->decimal('baseImponibleACoste2', 10, 2)->nullable();
            $table->decimal('baseImponibleACoste3', 10, 2)->nullable();
            $table->decimal('baseImponibleACoste4', 10, 2)->nullable();
            $table->decimal('cuotaRepercutida', 10, 2);
            $table->decimal('cuotaRepercutida2', 10, 2)->nullable();
            $table->decimal('cuotaRepercutida3', 10, 2)->nullable();
            $table->decimal('cuotaRepercutida4', 10, 2)->nullable();
            $table->decimal('tipoRecargoEquivalencia', 10, 2);
            $table->decimal('tipoRecargoEquivalencia2', 10, 2)->nullable();
            $table->decimal('tipoRecargoEquivalencia3', 10, 2)->nullable();
            $table->decimal('tipoRecargoEquivalencia4', 10, 2)->nullable();
            $table->decimal('cuotaRecargoEquivalencia', 10, 2);
            $table->decimal('cuotaRecargoEquivalencia2', 10, 2)->nullable();
            $table->decimal('cuotaRecargoEquivalencia3', 10, 2)->nullable();
            $table->decimal('cuotaRecargoEquivalencia4', 10, 2)->nullable();
            $table->decimal('cuotaTotal', 10, 2);
            $table->decimal('importeTotal', 10, 2);
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
            $table->string('estado_proceso')->default('desbloqueada');
            
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
