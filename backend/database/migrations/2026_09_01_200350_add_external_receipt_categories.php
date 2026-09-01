<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        foreach ([
            'alugapro' => ['name' => 'Aluguel', 'color' => '#0ea5e9'],
            'dashpay' => ['name' => 'credpix', 'color' => '#8b5cf6'],
        ] as $integration => $category) {
            $categoryId = DB::table('categories')
                ->whereNull('user_id')
                ->where('name', $category['name'])
                ->where('kind', 'income')
                ->value('id');

            if (! $categoryId) {
                $categoryId = DB::table('categories')->insertGetId([
                    'user_id' => null,
                    'name' => $category['name'],
                    'kind' => 'income',
                    'color' => $category['color'],
                    'icon' => null,
                    'active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            DB::table('finance_records')
                ->where('source', 'api:'.$integration)
                ->update([
                    'category_id' => $categoryId,
                    'updated_at' => $now,
                ]);
        }
    }

    /**
     * Keep category assignments to avoid erasing financial classification on rollback.
     */
    public function down(): void {}
};
