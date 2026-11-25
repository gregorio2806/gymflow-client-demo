<?php

namespace App\Models;

use App\Models\Concerns\BelongsToGym;
use App\Tenancy\CurrentGym;
use Database\Factories\BranchFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A physical location of a gym. Every gym has exactly one main branch;
 * Multi-Branch plans can have more.
 *
 * @property int $id
 * @property int $gym_id
 * @property string $name
 * @property string|null $address
 * @property string|null $phone
 * @property bool $is_main
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Branch extends Model
{
    /** @use HasFactory<BranchFactory> */
    use BelongsToGym, HasFactory;

    protected $fillable = ['name', 'address', 'phone'];

    protected static function booted(): void
    {
        static::addGlobalScope('branch', fn (Builder $query) => app(CurrentGym::class)->restrictToBranch($query, 'id'));
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_main' => 'boolean',
            'is_active' => 'boolean',
        ];
    }
}
