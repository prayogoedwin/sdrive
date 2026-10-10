<?php

namespace App\Models;

use App\Enums\DriveItemType;
use App\Enums\DriveSpace;
use App\Enums\SharePermission;
use Database\Factories\DriveItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'parent_id',
    'owner_id',
    'created_by',
    'name',
    'type',
    'space',
    'mime_type',
    'extension',
    'size',
    'storage_path',
    'office_version',
    'path',
    'trash_batch',
    'link_token',
    'link_permission',
])]
class DriveItem extends Model
{
    /** @use HasFactory<DriveItemFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => DriveItemType::class,
            'space' => DriveSpace::class,
            'link_permission' => SharePermission::class,
            'size' => 'integer',
            'office_version' => 'integer',
            'parent_id' => 'integer',
            'owner_id' => 'integer',
            'created_by' => 'integer',
        ];
    }

    public function isFolder(): bool
    {
        return $this->type === DriveItemType::Folder;
    }

    public function isFile(): bool
    {
        return $this->type === DriveItemType::File;
    }

    public function isCommon(): bool
    {
        return $this->space === DriveSpace::Common;
    }

    /**
     * @return list<int>
     */
    public function ancestorIds(): array
    {
        if ($this->path === null || $this->path === '') {
            return [];
        }

        return array_values(array_map(intval(...), explode('/', $this->path)));
    }

    public function pathForChild(): string
    {
        return $this->path ? $this->path.'/'.$this->id : (string) $this->id;
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function shares(): HasMany
    {
        return $this->hasMany(DriveShare::class);
    }

    public function accessRequests(): HasMany
    {
        return $this->hasMany(DriveAccessRequest::class);
    }

    public function stars(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'drive_stars')->withTimestamps();
    }

    /**
     * @param  Builder<DriveItem>  $query
     * @return Builder<DriveItem>
     */
    public function scopeInFolder(Builder $query, ?int $parentId): Builder
    {
        if ($parentId === null) {
            return $query->whereNull('parent_id');
        }

        return $query->where('parent_id', $parentId);
    }
}
