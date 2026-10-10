<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DriveOpen extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'drive_item_id',
        'opened_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'opened_at' => 'datetime',
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
}
