<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Marca los registros cuyos UTM no se capturaron en el formulario, sino que
 * se reconstruyeron después a partir del access log de nginx. Son datos
 * inferidos y conviene poder distinguirlos de los originales.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('submissions', function (Blueprint $table) {
            $table->timestamp('utms_reconstructed_at')->nullable()->after('utm_content');
        });
    }

    public function down(): void
    {
        Schema::table('submissions', function (Blueprint $table) {
            $table->dropColumn('utms_reconstructed_at');
        });
    }
};
