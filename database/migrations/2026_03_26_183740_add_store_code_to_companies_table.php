<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private string $charset = 'ABCDEFGHJKLMNPQRSTUVWXYZ';

    public function up(): void
    {
        Schema::table('companies', static function (Blueprint $table) {
            $table->char('store_code', 5)->nullable()->unique()->after('id');
        });

        $this->generateForExistingCompanies();

        Schema::table('companies', static function (Blueprint $table) {
            $table->char('store_code', 5)->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('companies', static function (Blueprint $table) {
            $table->dropUnique(['store_code']);
            $table->dropColumn('store_code');
        });
    }

    private function generateForExistingCompanies(): void
    {
        $ids = DB::table('companies')->pluck('id');

        foreach ($ids as $id) {
            do {
                $code = $this->generateCode();
            } while (DB::table('companies')->where('store_code', $code)->exists());

            DB::table('companies')->where('id', $id)->update(['store_code' => $code]);
        }
    }

    private function generateCode(): string
    {
        $code = '';
        $len  = strlen($this->charset) - 1;

        for ($i = 0; $i < 5; $i++) {
            $code .= $this->charset[random_int(0, $len)];
        }

        return $code;
    }
};
