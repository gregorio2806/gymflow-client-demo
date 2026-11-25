<?php

namespace App\Models;

use App\Models\Concerns\BelongsToGym;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * Append-only record of a sensitive change. Write entries with App\Support\Audit::log().
 *
 * @property int $id
 * @property int $gym_id
 * @property int|null $user_id
 * @property string $action
 * @property string|null $subject_type
 * @property int|null $subject_id
 * @property array{before?: array<string, mixed>, after?: array<string, mixed>}|null $changes
 * @property string|null $ip_address
 * @property Carbon $created_at
 */
class AuditLog extends Model
{
    use BelongsToGym;

    public const UPDATED_AT = null;

    protected $fillable = ['user_id', 'action', 'changes', 'ip_address'];

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Audit logs cannot be changed.'));
        static::deleting(fn () => throw new LogicException('Audit logs cannot be deleted.'));
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'changes' => 'array',
            'created_at' => 'datetime',
        ];
    }
}
