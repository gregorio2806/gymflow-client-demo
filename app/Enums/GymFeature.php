<?php

namespace App\Enums;

/**
 * Modules the platform admin can switch on/off per gym (stored with Laravel Pennant).
 *
 * Adding a module: add a case here, protect its routes with ->middleware('feature:<value>'),
 * and hide its nav item when it's not in the shared `features` prop.
 */
enum GymFeature: string
{
    case Members = 'members';
    case QrAttendance = 'qr-attendance';
    case Payments = 'payments';
    case Website = 'website';
    case MemberPortal = 'member-portal';
    case MemberAuth = 'member-auth';
    case Workouts = 'workouts';
    case CoachCalendar = 'coach-calendar';
    case CalorieHelper = 'calorie-helper';
    case Reports = 'reports';
    case OnlinePayments = 'online-payments';
    case EmailReminders = 'email-reminders';
    case StaffActivityLog = 'staff-activity-log';
    case FullReports = 'full-reports';

    public function label(): string
    {
        return match ($this) {
            self::Members => 'Members & memberships',
            self::QrAttendance => 'QR attendance',
            self::Payments => 'Payments',
            self::Website => 'Gym website',
            self::MemberPortal => 'Member portal',
            self::MemberAuth => 'Member login',
            self::Workouts => 'Workouts',
            self::CoachCalendar => 'Coach calendar',
            self::CalorieHelper => 'Calorie & goal helper',
            self::Reports => 'Reports',
            self::OnlinePayments => 'Online payments',
            self::EmailReminders => 'Email expiry reminders',
            self::StaffActivityLog => 'Staff activity log',
            self::FullReports => 'Full reports & exports',
        };
    }

    /**
     * Whether a gym gets this feature until the platform admin changes it.
     */
    public function enabledByDefault(?Plan $plan = null): bool
    {
        if ($this === self::MemberAuth) {
            // Member authentication is intentionally paused until the portal work resumes.
            return false;
        }

        if ($this->dependsOnPlan()) {
            return in_array($this->value, config('plans.plans.'.($plan ?? Plan::Starter)->value.'.features', []), true);
        }

        return $this !== self::CalorieHelper; // health data — opt-in only
    }

    public function dependsOnPlan(): bool
    {
        return in_array($this, [self::OnlinePayments, self::EmailReminders, self::StaffActivityLog, self::FullReports], true);
    }
}
