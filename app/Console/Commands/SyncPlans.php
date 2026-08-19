<?php

namespace App\Console\Commands;

use App\Models\Organization;
use App\Notifications\PlanExpiring;
use Illuminate\Console\Command;

/**
 * Хугацаа нь дууссан эрхийн үр дагаврыг хэрэгжүүлнэ:
 * Бизнес эрхийн «Баталгаажсан» тэмдгийг буцаана.
 * (Салбарууд идэвхтэй үлдэнэ — зөвхөн шинээр нэмэхийг лимит хорино.)
 */
class SyncPlans extends Command
{
    protected $signature = 'plans:sync';

    protected $description = 'Дууссан эрхийн badge зэрэг үр дагаврыг хэрэгжүүлэх';

    public function handle(): int
    {
        $revoked = 0;

        Organization::where('plan', '!=', 'free')
            ->where('plan_expires_at', '<=', now())
            ->whereHas('businesses', fn ($q) => $q->where('is_verified', true))
            ->eachById(function (Organization $organization) use (&$revoked) {
                $revoked += $organization->businesses()->where('is_verified', true)->update(['is_verified' => false]);
            });

        $this->info("{$revoked} бизнесийн баталгаажсан тэмдэг буцаагдлаа.");

        // 7 хоногийн дараа дуусах эрхийн сануулга (өдөрт нэг удаа ажилладаг тул давхардахгүй)
        Organization::where('plan', '!=', 'free')
            ->whereBetween('plan_expires_at', [now()->addDays(6), now()->addDays(7)])
            ->with('owner')
            ->eachById(fn (Organization $organization) => $organization->owner?->notify(new PlanExpiring($organization)));

        return self::SUCCESS;
    }
}
