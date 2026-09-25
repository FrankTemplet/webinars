<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            // Pie de página compartido por las landings de webinar, la página
            // de gracias y las encuestas del cliente.
            $table->text('footer_text')->nullable()->after('logo');
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn('footer_text');
        });
    }
};
