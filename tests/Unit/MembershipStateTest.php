<?php

namespace Tests\Unit;

use App\Enums\MembershipState;
use App\Enums\MemberSubscriptionStatus;
use App\Models\MemberSubscription;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class MembershipStateTest extends TestCase
{
    public static function boundaries(): array
    {
        return [
            'start inclusive' => ['2026-10-05 00:00:00', '2026-11-05 00:00:00', '2026-10-05 00:00:00', 'active', MembershipState::Active],
            'before start' => ['2026-10-05 00:00:00', '2026-11-05 00:00:00', '2026-10-04 23:59:59', 'active', MembershipState::Scheduled],
            'scheduled short period' => ['2026-10-06 00:00:00', '2026-10-07 00:00:00', '2026-10-05 12:00:00', 'active', MembershipState::Scheduled],
            'last valid second' => ['2026-09-05 00:00:00', '2026-10-05 00:00:00', '2026-10-04 23:59:59', 'active', MembershipState::ExpiringSoon],
            'end exclusive' => ['2026-09-05 00:00:00', '2026-10-05 00:00:00', '2026-10-05 00:00:00', 'active', MembershipState::Expired],
            'expired suspended' => ['2026-09-05 00:00:00', '2026-10-05 00:00:00', '2026-10-05 00:00:00', 'suspended', MembershipState::Expired],
            'suspended before expiry' => ['2026-09-05 00:00:00', '2026-10-05 00:00:00', '2026-10-04 12:00:00', 'suspended', MembershipState::Suspended],
            'cancelled before expiry' => ['2026-09-05 00:00:00', '2026-10-05 00:00:00', '2026-10-04 12:00:00', 'cancelled', MembershipState::Cancelled],
            'cancelled after expiry' => ['2026-09-05 00:00:00', '2026-10-05 00:00:00', '2026-10-05 12:00:00', 'cancelled', MembershipState::Cancelled],
            'seven day valid-through boundary' => ['2026-10-01 00:00:00', '2026-10-13 00:00:00', '2026-10-05 12:00:00', 'active', MembershipState::ExpiringSoon],
            'eight day valid-through date' => ['2026-10-01 00:00:00', '2026-10-14 00:00:00', '2026-10-05 12:00:00', 'active', MembershipState::Active],
            'warning starts at Manila midnight' => ['2026-10-01 00:00:00', '2026-10-14 00:00:00', '2026-10-06 00:00:00', 'active', MembershipState::ExpiringSoon],
            'month-end warning' => ['2026-01-01 00:00:00', '2026-02-01 00:00:00', '2026-01-24 12:00:00', 'active', MembershipState::ExpiringSoon],
            'leap day valid' => ['2024-02-01 00:00:00', '2024-03-01 00:00:00', '2024-02-29 23:59:59', 'active', MembershipState::ExpiringSoon],
            'leap year March rollover' => ['2024-02-01 00:00:00', '2024-03-01 00:00:00', '2024-03-01 00:00:00', 'active', MembershipState::Expired],
            'non-leap year February end' => ['2025-02-01 00:00:00', '2025-03-01 00:00:00', '2025-02-28 23:59:59', 'active', MembershipState::ExpiringSoon],
            'year rollover' => ['2026-12-01 00:00:00', '2027-01-01 00:00:00', '2027-01-01 00:00:00', 'active', MembershipState::Expired],
        ];
    }

    #[DataProvider('boundaries')]
    public function test_status_boundaries_in_gym_local_time(string $start, string $end, string $at, string $status, MembershipState $expected): void
    {
        $period = new MemberSubscription;
        $period->forceFill(['starts_at' => CarbonImmutable::parse($start, 'Asia/Manila')->utc(), 'ends_at' => CarbonImmutable::parse($end, 'Asia/Manila')->utc(), 'status' => MemberSubscriptionStatus::from($status)]);
        $instant = CarbonImmutable::parse($at, 'Asia/Manila')->utc();
        $this->assertSame($expected, $period->membershipState($instant, 'Asia/Manila'));
        $this->assertSame($expected->label(), $period->displayStatus($instant, 'Asia/Manila'));
    }

    public function test_warning_window_uses_supplied_timezone_and_can_be_configured(): void
    {
        $period = new MemberSubscription;
        $period->forceFill(['starts_at' => CarbonImmutable::parse('2026-10-01', 'Asia/Manila')->utc(), 'ends_at' => CarbonImmutable::parse('2026-10-14', 'Asia/Manila')->utc(), 'status' => MemberSubscriptionStatus::Active]);
        $at = CarbonImmutable::parse('2026-10-05 16:30:00', 'UTC');
        $this->assertSame(MembershipState::ExpiringSoon, $period->membershipState($at, 'Asia/Manila'));
        $this->assertSame(MembershipState::Active, $period->membershipState($at, 'UTC'));
        config(['memberships.expiring_soon_days' => 3]);
        $this->assertSame(MembershipState::Active, $period->membershipState($at, 'Asia/Manila'));
    }

    public function test_zero_day_window_warns_only_on_last_valid_date(): void
    {
        config(['memberships.expiring_soon_days' => 0]);
        $period = new MemberSubscription;
        $period->forceFill(['starts_at' => CarbonImmutable::parse('2026-10-01', 'Asia/Manila')->utc(), 'ends_at' => CarbonImmutable::parse('2026-10-06', 'Asia/Manila')->utc(), 'status' => MemberSubscriptionStatus::Active]);
        $this->assertSame(MembershipState::Active, $period->membershipState(CarbonImmutable::parse('2026-10-04 12:00:00', 'Asia/Manila'), 'Asia/Manila'));
        $this->assertSame(MembershipState::ExpiringSoon, $period->membershipState(CarbonImmutable::parse('2026-10-05 12:00:00', 'Asia/Manila'), 'Asia/Manila'));
    }
}
