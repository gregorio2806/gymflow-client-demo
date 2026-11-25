<?php

namespace App\Models;

use App\Enums\GymStatus;
use App\Enums\Plan;
use App\Support\WebsitePageCache;
use App\Tenancy\CurrentGym;
use Database\Factories\GymFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string $timezone Fixed to Asia/Manila for now — not fillable, not editable.
 * @property string|null $email
 * @property string|null $phone
 * @property string|null $address
 * @property array<string, mixed>|null $branding
 * @property GymStatus $status
 * @property Carbon|null $demo_expires_at Set only for "Try demo" gyms; never fillable.
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'slug', 'email', 'phone', 'address', 'branding'])]
class Gym extends Model
{
    /** @use HasFactory<GymFactory> */
    use HasFactory;

    /**
     * Slugs that can't be used because they clash with platform subdomains.
     */
    public const RESERVED_SLUGS = [
        'www', 'app', 'admin', 'api', 'mail', 'smtp', 'ftp', 'portal', 'dashboard',
        'login', 'register', 'support', 'help', 'status', 'billing', 'static', 'assets', 'cdn',
    ];

    protected $attributes = [
        'timezone' => 'Asia/Manila',
        'status' => 'active',
    ];

    protected static function booted(): void
    {
        static::creating(function (Gym $gym) {
            $gym->slug = static::uniqueSlug($gym->slug ?: $gym->name);
        });

        // Every gym starts with its main branch. runAs: the branch must belong to THIS gym,
        // even when another gym is current (e.g. an owner creating a second gym).
        static::created(function (Gym $gym) {
            app(CurrentGym::class)->runAs($gym, function () use ($gym) {
                $branch = new Branch(['name' => 'Main branch', 'address' => $gym->address, 'phone' => $gym->phone]);
                $branch->is_main = true;
                $branch->save();

                (new GymSubscription)->save(); // Starter, trialing — callers change the plan if needed
            });
        });

        // MariaDB checks branch restrictions before completing the gym's cascades.
        static::deleting(fn (Gym $gym) => app(CurrentGym::class)->runAs($gym, function () use ($gym) {
            Payment::where('gym_id', $gym->id)->delete();
            DB::table('session_redemptions')->where('gym_id', $gym->id)->delete();
            MemberPurchase::where('gym_id', $gym->id)->delete();
            $gym->members()->delete();
        }));
        static::deleted(fn (Gym $gym) => DB::afterCommit(fn () => Storage::disk('local')->deleteDirectory('websites/'.$gym->id)));
        static::deleted(fn (Gym $gym) => DB::afterCommit(fn () => WebsitePageCache::forget($gym->id)));
    }

    /**
     * Build a subdomain-safe slug that isn't reserved or already taken.
     */
    public static function uniqueSlug(string $value): string
    {
        $base = Str::limit(Str::slug($value), 50, '') ?: 'gym';
        $slug = $base;

        for ($i = 2; in_array($slug, self::RESERVED_SLUGS, true) || static::where('slug', $slug)->exists(); $i++) {
            $slug = "{$base}-{$i}";
        }

        return $slug;
    }

    public function isDemo(): bool
    {
        return $this->demo_expires_at !== null;
    }

    /**
     * All locations of this gym. Not filtered by the current-gym scope's gym — this
     * relation is already limited to this gym.
     *
     * @return HasMany<Branch, $this>
     */
    public function branches(): HasMany
    {
        return $this->hasMany(Branch::class)->withoutGlobalScope('gym');
    }

    /**
     * @return HasOne<GymSubscription, $this>
     */
    public function subscription(): HasOne
    {
        return $this->hasOne(GymSubscription::class)->withoutGlobalScope('gym');
    }

    public function plan(): Plan
    {
        return $this->subscription->plan;
    }

    public function mainBranch(): Branch
    {
        return $this->branches()->where('is_main', true)->firstOrFail();
    }

    /** @return HasMany<Member, $this> */
    public function members(): HasMany
    {
        return $this->hasMany(Member::class);
    }

    /**
     * Owners, staff, and coaches of this gym.
     *
     * @return BelongsToMany<User, $this, GymUser>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->using(GymUser::class)
            ->withPivot('role', 'branch_id', 'branch_ids', 'is_active', 'availability', 'availability_revision')
            ->withTimestamps();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'branding' => 'array',
            'pricing_settings' => 'array',
            'feature_overrides' => 'array',
            'needs_staff_selection' => 'boolean',
            'needs_branch_selection' => 'boolean',
            'status' => GymStatus::class,
            'demo_expires_at' => 'datetime',
        ];
    }
}
