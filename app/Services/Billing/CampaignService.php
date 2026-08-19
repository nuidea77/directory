<?php

namespace App\Services\Billing;

use App\Models\Campaign;
use Illuminate\Support\Facades\Cache;

/**
 * Онцлох зайн амьдралын мөчлөг: дууссаныг хаах, дараалалд байгааг
 * зай суларсан үед FIFO дарааллаар идэвхжүүлэх.
 */
class CampaignService
{
    /**
     * Cheap opportunistic sync — public featured жагсаалт татахад дуудагдана.
     * 60 секундэд нэгээс олонгүй ажиллана.
     */
    public function sync(): void
    {
        if (! Cache::add('campaigns:sync-lock', 1, 60)) {
            return;
        }

        $this->syncNow();
    }

    public function syncNow(): void
    {
        // 1. Хугацаа дууссаныг хаана
        Campaign::where('status', 'active')->where('ends_at', '<=', now())->update(['status' => 'expired']);

        // 2. Дараалалд байгааг зай гарвал идэвхжүүлнэ
        Campaign::where('status', 'queued')
            ->orderBy('created_at')
            ->get()
            ->groupBy(fn (Campaign $c) => implode('|', [$c->type, $c->category_id, $c->district, $c->city, $c->keyword]))
            ->each(function ($queue) {
                /** @var Campaign $first */
                $first = $queue->first();
                $slots = (int) config("billing.ads.{$first->type}.slots", 3);

                foreach ($queue as $campaign) {
                    $free = Campaign::firstFreeSlot(
                        $campaign->type,
                        $slots,
                        $campaign->category_id,
                        $campaign->district,
                        $campaign->city,
                        $campaign->keyword,
                    );

                    if ($free === null) {
                        break;
                    }

                    $this->activate($campaign, $free);
                }
            });
    }

    public function activate(Campaign $campaign, ?int $slot = null): void
    {
        $slots = (int) config("billing.ads.{$campaign->type}.slots", 3);

        $campaign->update([
            'status' => 'active',
            'slot' => $slot ?? Campaign::firstFreeSlot(
                $campaign->type, $slots, $campaign->category_id, $campaign->district, $campaign->city, $campaign->keyword,
            ) ?? $campaign->slot ?? 1,
            'starts_at' => now(),
            'ends_at' => now()->addDays($campaign->days),
        ]);
    }

    /**
     * Төлбөр батлагдсаны дараа: зай сул бол шууд идэвхжүүлнэ, дүүрсэн бол дараалалд.
     * Зэрэг орж ирсэн төлбөрүүд нэг зайг хоёуланг нь эзлэхгүйн тулд түгжээтэй.
     */
    public function activateOrQueue(Campaign $campaign): void
    {
        $slots = (int) config("billing.ads.{$campaign->type}.slots", 3);

        \Illuminate\Support\Facades\DB::transaction(function () use ($campaign, $slots) {
            // Тухайн орон зайн мөрүүдийг түгжиж байж дугаар хуваарилна
            Campaign::query()
                ->inSlotSpace($campaign->type, $campaign->category_id, $campaign->district, $campaign->city, $campaign->keyword)
                ->holdingSlot()
                ->lockForUpdate()
                ->get(['id']);

            $free = Campaign::firstFreeSlot(
                $campaign->type, $slots, $campaign->category_id, $campaign->district, $campaign->city, $campaign->keyword,
            );

            if ($free !== null) {
                $this->activate($campaign, $free);
            } else {
                $campaign->update(['status' => 'queued']);
            }
        });
    }

    /**
     * Зайн төлөв: [нийт, эзэлсэн, дараалалд, төлбөр хүлээж буй].
     * $ignoreCampaignId — өөрийнхөө мөрийг тоолохгүй (дахин шалгах үед).
     */
    public function slotState(string $type, ?int $categoryId = null, ?string $district = null, ?string $city = null, ?string $keyword = null): array
    {
        $this->sync();

        $slots = (int) config("billing.ads.{$type}.slots", 3);

        $running = Campaign::query()->running()
            ->inSlotSpace($type, $categoryId, $district, $city, $keyword)
            ->orderBy('slot')
            ->get(['id', 'slot', 'ends_at', 'business_id']);

        $queued = Campaign::query()
            ->where('status', 'queued')
            ->inSlotSpace($type, $categoryId, $district, $city, $keyword)
            ->count();

        // Төлбөр хүлээж буй нь зайг түр барина — эс бөгөөс нэг зайг олон
        // хүнд зэрэг зарж, дараа нь бүгд дараалалд гацдаг
        $pending = Campaign::query()
            ->where('status', 'pending_payment')
            ->inSlotSpace($type, $categoryId, $district, $city, $keyword)
            ->count();

        return [
            'total' => $slots,
            'occupied' => $running->count(),
            'queued' => $queued,
            'pending' => $pending,
            'running' => $running,
        ];
    }
}
