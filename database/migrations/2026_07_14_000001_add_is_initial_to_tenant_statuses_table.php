<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenant_statuses', function (Blueprint $table) {
            $table->boolean('is_initial')->default(false)->after('order');
        });

        // Backfill: marca come iniziale lo stato con order minimo di ogni tenant,
        // così ogni tenant esistente ha già uno stato iniziale valido subito dopo la migrazione.
        DB::table('tenant_statuses')
            ->select('tenant_id', DB::raw('MIN(`order`) as min_order'))
            ->groupBy('tenant_id')
            ->get()
            ->each(function ($row) {
                DB::table('tenant_statuses')
                    ->where('tenant_id', $row->tenant_id)
                    ->where('order', $row->min_order)
                    ->limit(1)
                    ->update(['is_initial' => true]);
            });
    }

    public function down(): void
    {
        Schema::table('tenant_statuses', function (Blueprint $table) {
            $table->dropColumn('is_initial');
        });
    }
};
