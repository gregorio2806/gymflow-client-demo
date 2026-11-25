<?php

/*
|--------------------------------------------------------------------------
| Subscription plans — the single source of truth
|--------------------------------------------------------------------------
|
| Used by the landing page today, and by plan limits / billing later, so prices
| and limits are only ever changed here. Prices are whole Philippine pesos.
| `null` limit = unlimited.
|
*/

return [

    // "Pay 10 months, get 12": yearly price = monthly price × 10.
    'yearly_months_charged' => 10,

    'plans' => [

        'starter' => [
            'features' => [],
            'name' => 'Starter',
            'tagline' => 'For a single small gym getting off paper.',
            'monthly_price' => 999,
            'limits' => [
                'branches' => 1,
                'staff_accounts' => 2,
                'active_members' => 150,
            ],
            'highlights' => [
                '1 branch · 1 owner + 2 staff',
                'Up to 150 active members',
            ],
        ],

        'pro' => [
            'features' => ['online-payments', 'email-reminders', 'staff-activity-log', 'full-reports'],
            'name' => 'Pro',
            'tagline' => 'For busy gyms that need more staff and detailed reports.',
            'monthly_price' => 1999,
            'popular' => true,
            'limits' => [
                'branches' => 1,
                'staff_accounts' => 5,
                'active_members' => 500,
            ],
            'highlights' => [
                '1 branch · 1 owner + 5 staff',
                'Up to 500 active members',
            ],
        ],

        'multi_branch' => [
            'features' => ['online-payments', 'email-reminders', 'staff-activity-log', 'full-reports'],
            'name' => 'Multi-Branch',
            'tagline' => 'For gyms with more than one location.',
            'monthly_price' => 2999,
            'extra_branch_monthly_price' => 999,
            'limits' => [
                'branches' => 2, // included; more at extra_branch_monthly_price each
                'staff_accounts_per_branch' => 5,
                'active_members' => null,
            ],
            'highlights' => [
                '2 branches included · 5 staff per branch',
                'Unlimited active members',
            ],
        ],

    ],

    /*
     | Feature list shown on EVERY pricing card, grouped by category:
     | [label, starter, pro, multi_branch] — true = included, false = not included
     | (shown grayed out with ✕), string = included with that plan's own wording.
     */
    'card_features' => [
        'Members & payments' => [
            ['Membership & expiry tracking', true, true, true],
            ['Manual sales recording', true, true, true],
        ],
        'Reports & team' => [
            ['Reports', 'Reports: up to 31 days', 'Reports: up to 366 days + CSV export', 'Gym-wide / branch reports + CSV export'],
            ['Workout routines & coach calendar', true, true, true],
        ],
        'Gym website' => [
            ['Gym website & member portal', true, true, true],
        ],
        'Branches' => [
            ['Branch managers (see own branch only)', false, false, true],
            ['Members can use any branch', false, false, true],
            ['Move members and staff between branches', false, false, true],
        ],
    ],

    /*
     | Full comparison table: [feature, starter, pro, multi_branch].
     | true = included, false = not included, null = not applicable, string = detail.
     */
    'comparison' => [
        ['Branches', '1', '1', '2 included (+₱999/mo each extra)'],
        ['Owner account', '1', '1', '1 + branch managers'],
        ['Staff accounts', '2', '5', '5 per branch'],
        ['Active members', 'Up to 150', 'Up to 500', 'Unlimited'],
        ['Membership & expiry tracking', true, true, true],
        ['Manual sales recording', true, true, true],
        ['Daily / monthly reports', 'Up to 31 days', 'Up to 366 days + CSV export', 'Gym-wide / branch filters + CSV export'],
        ['Gym website & member portal', true, true, true],
        ['Workout routines & coach calendar', true, true, true],
        ['Members can use any branch', null, null, true],
        ['Branch managers (see own branch only)', null, null, true],
        ['Move members / staff between branches', null, null, true],
    ],

];
