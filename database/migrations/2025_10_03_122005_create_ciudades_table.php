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
        Schema::create('poblaciones', function (Blueprint $table) {
            $table->string('codigo_x', 8);
            $table->string('cp', 10);
            $table->string('poblacion', 100);
            $table->string('provincia_nombre', 100);
            $table->string('pais', 10);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ciudades');
    }
};
