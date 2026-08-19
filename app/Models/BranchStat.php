<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BranchStat extends Model
{
    protected $fillable = [
        'branch_id', 'date', 'views', 'calls', 'directions',
        'views_category', 'views_search', 'views_map', 'views_direct',
    ];

    protected function casts(): array
    {
        return ['date' => 'date'];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
