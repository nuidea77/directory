<?php

namespace App\Console\Commands;

use App\Services\Billing\CampaignService;
use Illuminate\Console\Command;

/**
 * Дууссан кампанит ажлыг хааж, дараалалд буйг идэвхжүүлнэ.
 * Cron-оор 5 минут тутам ажиллана — траффикаас хамаарахгүй.
 */
class SyncCampaigns extends Command
{
    protected $signature = 'campaigns:sync';

    protected $description = 'Онцлох байршлын кампанит ажлын төлвийг шинэчлэх (expire + queue promote)';

    public function handle(CampaignService $campaigns): int
    {
        $campaigns->syncNow();

        $this->info('Кампанит ажлын төлөв шинэчлэгдлээ.');

        return self::SUCCESS;
    }
}
