<?php

namespace App\Models;

use App\Enums\SharePermission;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['drive_item_id', 'user_id', 'shared_by', 'permission'])]
class DriveShare extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'permission' => SharePermission::class,
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

    public function sharer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'shared_by');
    }
}
