<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Services\Billing\BillingService;
use Illuminate\Console\Command;

/**
 * 24 цагаас дээш төлөгдөөгүй захиалгыг хааж,
 * төлбөр хүлээж байсан кампанит ажлуудыг цуцална.
 */
class ExpireOrders extends Command
{
    protected $signature = 'orders:expire {--hours=24 : Хэдэн цагийн дараа хаах}';

    protected $description = 'Хугацаа хэтэрсэн pending захиалгуудыг хаах';

    public function handle(BillingService $billing): int
    {
        $count = 0;

        Order::where('status', 'pending')
            ->where('created_at', '<', now()->subHours((int) $this->option('hours')))
            ->each(function (Order $order) use ($billing, &$count) {
                // Сүүлийн мөчид төлөгдсөн байж болзошгүй — эхлээд byl-тэй тулгана
                $order = $billing->sync($order);

                if ($order->status === 'pending') {
                    $billing->voidOrder($order, status: 'expired');
                    $count++;
                }
            });

        $this->info("{$count} захиалга хаагдлаа.");

        return self::SUCCESS;
    }
}
