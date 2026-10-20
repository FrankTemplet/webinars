<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Borrado lógico para las tres entidades que arrastran datos al borrarse.
 *
 * `submissions` y `attendees` cuelgan de `webinars` con cascadeOnDelete, y
 * `survey_responses` de `surveys`: un Delete en el panel se llevaba por
 * delante cientos de registros sin forma de recuperarlos. Con deleted_at el
 * DELETE pasa a ser un UPDATE y las claves foráneas ya no se disparan.
 */
return new class extends Migration
{
    private const TABLES = ['webinars', 'surveys', 'clients'];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->softDeletes();
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->dropSoftDeletes();
            });
        }
    }
};
