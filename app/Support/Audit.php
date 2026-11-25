<?php

namespace App\Support;

use App\Models\AuditLog;
use App\Models\Gym;
use App\Tenancy\CurrentGym;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

class Audit
{
    /**
     * Record a sensitive action in the current gym's audit log.
     *
     * Call it AFTER saving the subject: its before/after values are taken from the save
     * (hidden attributes such as passwords and tokens are never recorded).
     *
     * @param  array{before?: array<string, mixed>, after?: array<string, mixed>}|null  $changes  overrides the automatic diff
     */
    public static function log(string $action, ?Model $subject = null, ?array $changes = null, ?int $actorId = null): AuditLog
    {
        $log = new AuditLog([
            'user_id' => $actorId ?? auth()->id(),
            'action' => $action,
            'changes' => $changes ?? ($subject ? self::diff($subject) : null),
            'ip_address' => request()->ip(),
        ]);

        if ($subject) {
            $log->subject()->associate($subject);
        }

        $log->save();

        return $log;
    }

    /**
     * Record an action in a specific gym's audit log — for platform admin actions taken
     * from outside that gym (e.g. toggling its features).
     *
     * @param  array{before?: array<string, mixed>, after?: array<string, mixed>}|null  $changes
     */
    public static function logForGym(Gym $gym, string $action, ?Model $subject = null, ?array $changes = null): AuditLog
    {
        return app(CurrentGym::class)->runAs($gym, fn () => self::log($action, $subject, $changes));
    }

    /**
     * @return array{before: array<string, mixed>, after: array<string, mixed>}|null
     */
    private static function diff(Model $subject): ?array
    {
        $ignored = [...$subject->getHidden(), $subject->getUpdatedAtColumn(), $subject->getCreatedAtColumn()];
        $after = Arr::except($subject->getChanges(), $ignored);

        if ($after === []) {
            return null;
        }

        return [
            'before' => Arr::only($subject->getPrevious(), array_keys($after)),
            'after' => $after,
        ];
    }
}
