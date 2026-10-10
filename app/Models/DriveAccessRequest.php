<?php

namespace App\Models;

use App\Enums\AccessRequestStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'drive_item_id',
    'user_id',
    'status',
    'resolved_by',
    'resolved_at',
])]
class DriveAccessRequest extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => AccessRequestStatus::class,
            'resolved_at' => 'datetime',
            'drive_item_id' => 'integer',
            'user_id' => 'integer',
            'resolved_by' => 'integer',
        ];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(DriveItem::class, 'drive_item_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }
}
