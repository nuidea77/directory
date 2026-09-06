<?php

namespace Database\Seeders;

use App\Models\PaymentApp;
use Illuminate\Database\Seeder;

/**
 * config/payments.php-ээс анхны жагсаалтыг хүснэгтэд буулгана.
 * Байгаа мөрийг дарж бичихгүй — админаас хийсэн засварыг хадгална.
 */
class PaymentAppSeeder extends Seeder
{
    public function run(): void
    {
        $order = 0;

        foreach (config('payments', []) as $app) {
            PaymentApp::firstOrCreate(
                ['slug' => $app['slug']],
                [
                    'name' => $app['name'],
                    'color' => $app['color'] ?? '#566a65',
                    'sort_order' => $order,
                ],
            );

            $order += 10;
        }
    }
}
