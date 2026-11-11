<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const MOBILE_IMAGE = 'surveys/logos/01M763K8XNN7VXRWX2TN54801N.png';

    private function survey(): \Illuminate\Database\Query\Builder
    {
        return DB::table('surveys')
            ->where('client_id', DB::table('clients')->select('id')->where('slug', 'libertynet'))
            ->where('slug', 'ataque-ia-de-la-exposicion-a-la-prevencion');
    }

    public function up(): void
    {
        $this->survey()->where('header_logo', self::MOBILE_IMAGE)->whereNull('mobile_hero_image')
            ->update(['mobile_hero_image' => self::MOBILE_IMAGE, 'header_logo' => null]);
    }

    public function down(): void
    {
        $this->survey()->where('mobile_hero_image', self::MOBILE_IMAGE)->whereNull('header_logo')
            ->update(['header_logo' => self::MOBILE_IMAGE, 'mobile_hero_image' => null]);
    }
};
