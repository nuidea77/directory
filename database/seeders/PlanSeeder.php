<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

/**
 * config/billing.php-ийн анхны эрхийн бичгүүдийг хүснэгтэд буулгана.
 * Дараагийн өөрчлөлтүүд админ dashboard-оос хийгдэнэ.
 */
class PlanSeeder extends Seeder
{
    public function run(): void
    {
        $sort = 0;

        // Runtime config-ийг boot үед DB-ээс override хийдэг тул эндээс
        // авбал хуучин DB-ийн утга эргэж орно — заавал эх файлаас уншина.
        $base = require config_path('billing.php');

        foreach ($base['plans'] ?? [] as $key => $plan) {
            Plan::firstOrCreate(['key' => $key], [
                'name' => $plan['name'],
                'price' => $plan['price'],
                'price_monthly' => $plan['price_monthly'] ?? null,
                'term_years' => $plan['term_years'],
                'limits' => $plan['limits'],
                'analytics' => (bool) ($plan['analytics'] ?? false),
                'top_list' => (bool) ($plan['top_list'] ?? false),
                'verified_badge' => (bool) ($plan['verified_badge'] ?? false),
                'is_active' => true,
                'sort_order' => $sort++,
            ]);
        }

        Plan::flushCache();
    }
}
