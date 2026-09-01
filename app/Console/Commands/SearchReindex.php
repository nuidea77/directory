<?php

namespace App\Console\Commands;

use App\Services\SearchIndexer;
use Illuminate\Console\Command;

class SearchReindex extends Command
{
    protected $signature = 'search:reindex';

    protected $description = 'Салбаруудын хайлтын индексийг дахин үүсгэнэ';

    public function handle(SearchIndexer $indexer): int
    {
        $this->info('Хайлтын индексийг шинэчилж байна…');
        $count = $indexer->reindexAll();
        $this->info("{$count} салбар индексжлээ.");

        return self::SUCCESS;
    }
}
