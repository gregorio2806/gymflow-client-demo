<?php

use App\Http\Controllers\AccessRequestController;
use App\Http\Controllers\Dashboard\AppointmentController;
use App\Http\Controllers\Dashboard\AttendanceController;
use App\Http\Controllers\Dashboard\BranchController;
use App\Http\Controllers\Dashboard\BranchViewController;
use App\Http\Controllers\Dashboard\CheckInController;
use App\Http\Controllers\Dashboard\CoachCalendarController;
use App\Http\Controllers\Dashboard\CurrentGymController;
use App\Http\Controllers\Dashboard\DashboardController;
use App\Http\Controllers\Dashboard\GymController;
use App\Http\Controllers\Dashboard\GymSettingsController;
use App\Http\Controllers\Dashboard\MemberController;
use App\Http\Controllers\Dashboard\MemberPortalController;
use App\Http\Controllers\Dashboard\MemberPurchaseController;
use App\Http\Controllers\Dashboard\MemberQrController;
use App\Http\Controllers\Dashboard\MembershipPlanController;
use App\Http\Controllers\Dashboard\MemberSubscriptionController;
use App\Http\Controllers\Dashboard\PaymentController;
use App\Http\Controllers\Dashboard\PaymentOverviewController;
use App\Http\Controllers\Dashboard\PlanSelectionController;
use App\Http\Controllers\Dashboard\ProductInventoryController;
use App\Http\Controllers\Dashboard\ReportController;
use App\Http\Controllers\Dashboard\StaffController;
use App\Http\Controllers\Dashboard\WebsiteController;
use App\Http\Controllers\Dashboard\WebsiteInquiryController;
use App\Http\Controllers\Dashboard\WorkoutRoutineController;
use App\Http\Controllers\DemoController;
use App\Http\Controllers\InvitationController;
use App\Http\Controllers\LegalController;
use App\Http\Controllers\OwnerInvitationController;
use App\Http\Controllers\Platform\GymFeatureController;
use App\Http\Controllers\Platform\GymSubscriptionController;
use App\Http\Controllers\Platform\OwnerInvitationController as PlatformOwnerInvitationController;
use Illuminate\Support\Facades\Route;

// Register host-specific routes before the platform's general routes.
require __DIR__.'/gym-site.php';
require __DIR__.'/portal.php';

// Platform landing page — Blade for SEO and zero JavaScript.
// Every Blade page route needs `blade-page`, or Vue links/redirects to it open in a modal.
Route::view('/', 'marketing.landing')->middleware('blade-page')->name('home');
Route::get('{document}', [LegalController::class, 'show'])->whereIn('document', ['privacy', 'terms', 'cookies', 'refund', 'contact'])->middleware('blade-page')->name('legal.show');

// "Request access" form (sign-up is invite-only). Works without an email app or JavaScript.
Route::get('request-access', [AccessRequestController::class, 'create'])->middleware('blade-page')->name('access-requests.create');
Route::post('request-access', [AccessRequestController::class, 'store'])->middleware('throttle:access-request')->name('access-requests.store');

// "Try demo": private temporary demo gym (POST only, so crawlers can't create demos)
Route::post('demo', [DemoController::class, 'store'])->middleware(['guest', 'throttle:demo'])->name('demo.store');
Route::delete('demo', [DemoController::class, 'destroy'])->middleware('auth:web')->name('demo.destroy');

// Invitation links from email (token is the secret; rate-limited against guessing)
Route::middleware('throttle:20,1')->group(function () {
    Route::get('invitations/{token}', [InvitationController::class, 'show'])->name('invitations.show');
    Route::post('invitations/{token}/accept', [InvitationController::class, 'accept'])
        ->middleware('auth:web')
        ->name('invitations.accept');
    Route::post('invitations/{token}/register', [InvitationController::class, 'register'])
        ->middleware('guest')
        ->name('invitations.register');

    // Gym-owner access links (sign-up is invite-only; sent by the platform admin)
    Route::get('owner-invitations/{token}', [OwnerInvitationController::class, 'show'])->name('owner-invitations.show');
    Route::post('owner-invitations/{token}/accept', [OwnerInvitationController::class, 'accept'])
        ->middleware('auth:web')
        ->name('owner-invitations.accept');
    Route::post('owner-invitations/{token}/register', [OwnerInvitationController::class, 'register'])
        ->middleware('guest')
        ->name('owner-invitations.register');
});

Route::middleware(['auth:web', 'verified'])->group(function () {
    Route::get('gyms/create', [GymController::class, 'create'])->name('gyms.create');
    Route::post('gyms', [GymController::class, 'store'])->name('gyms.store');
    Route::put('current-gym', [CurrentGymController::class, 'update'])->name('current-gym.update');

    // Pages that need a current gym
    Route::middleware('has-gym')->group(function () {
        Route::put('current-branch', [BranchViewController::class, 'update'])->name('branch-view.update');
        Route::get('coach-calendar/coaches', [CoachCalendarController::class, 'roster'])->middleware('feature:coach-calendar')->name('coach-calendar.roster');
        Route::get('coach-calendar', [CoachCalendarController::class, 'index'])->middleware('feature:coach-calendar')->name('coach-calendar.index');
        Route::put('coach-calendar/availability/{coach}', [CoachCalendarController::class, 'availability'])->whereNumber('coach')->middleware(['feature:coach-calendar', 'throttle:30,1'])->name('coach-calendar.availability');
        Route::middleware('feature:coach-calendar')->group(function () {
            Route::get('appointments', [AppointmentController::class, 'index'])->name('appointments.index');
            Route::get('appointments/members', [AppointmentController::class, 'members'])->middleware('throttle:60,1')->name('appointments.members');
            Route::get('appointments/slots', [AppointmentController::class, 'slots'])->middleware('throttle:60,1')->name('appointments.slots');
            Route::post('appointments', [AppointmentController::class, 'store'])->middleware('throttle:30,1')->name('appointments.store');
            Route::patch('appointments/{schedule}', [AppointmentController::class, 'update'])->middleware('throttle:30,1')->name('appointments.update');
            Route::patch('appointments/{schedule}/status', [AppointmentController::class, 'status'])->middleware('throttle:30,1')->name('appointments.status');
        });
        Route::post('coach-calendar', [CoachCalendarController::class, 'store'])->middleware(['feature:coach-calendar', 'throttle:30,1'])->name('coach-calendar.store');
        Route::patch('coach-calendar/{schedule}', [CoachCalendarController::class, 'update'])->middleware(['feature:coach-calendar', 'throttle:30,1'])->name('coach-calendar.update');
        Route::post('coach-calendar/{schedule}/cancel', [CoachCalendarController::class, 'cancel'])->middleware(['feature:coach-calendar', 'throttle:30,1'])->name('coach-calendar.cancel');
        Route::middleware('feature:reports')->group(function () {
            Route::get('reports', [ReportController::class, 'index'])->name('reports.index');
            Route::post('reports/exports', [ReportController::class, 'store'])->middleware(['feature:full-reports', 'throttle:5,1'])->name('reports.export');
            Route::get('reports/exports/{export}/download', [ReportController::class, 'download'])->middleware('feature:full-reports')->name('reports.download');
        });
        Route::middleware('feature:workouts')->group(function () {
            Route::get('workout-routines', [WorkoutRoutineController::class, 'index'])->name('workout-routines.index');
            Route::get('workout-routines/create', [WorkoutRoutineController::class, 'create'])->name('workout-routines.create');
            Route::post('workout-routines', [WorkoutRoutineController::class, 'store'])->name('workout-routines.store');
            Route::get('workout-routines/{routine}/edit', [WorkoutRoutineController::class, 'edit'])->name('workout-routines.edit');
            Route::patch('workout-routines/{routine}', [WorkoutRoutineController::class, 'update'])->name('workout-routines.update');
            Route::post('workout-routines/{routine}/assignments', [WorkoutRoutineController::class, 'assign'])->middleware('throttle:30,1')->name('workout-routines.assign');
        });
        Route::get('dashboard', DashboardController::class)->name('dashboard');

        Route::get('product-inventory', [ProductInventoryController::class, 'index'])->name('product-inventory.index');
        Route::post('product-inventory', [ProductInventoryController::class, 'store'])->middleware('throttle:30,1')->name('product-inventory.store');
        Route::get('product-inventory/{product}/edit', [ProductInventoryController::class, 'edit'])->name('product-inventory.edit');
        Route::get('product-inventory/{product}', [ProductInventoryController::class, 'show'])->name('product-inventory.show');
        Route::patch('product-inventory/{product}', [ProductInventoryController::class, 'update'])->middleware('throttle:30,1')->name('product-inventory.update');
        Route::patch('product-inventory/{product}/archive', [ProductInventoryController::class, 'archive'])->middleware('throttle:30,1')->name('product-inventory.archive');
        Route::post('product-inventory/{product}/stock', [ProductInventoryController::class, 'adjustStock'])->middleware('throttle:30,1')->name('product-inventory.stock');

        Route::middleware('feature:member-auth')->group(function () {
            Route::get('members/{member}/portal', [MemberPortalController::class, 'show'])->name('member-portal.show');
            Route::post('members/{member}/portal', [MemberPortalController::class, 'store'])->middleware('throttle:20,1')->name('member-portal.store');
        });

        Route::middleware('feature:website')->group(function () {
            Route::get('gym/website', [WebsiteController::class, 'edit'])->name('website.edit');
            Route::get('gym/website/inquiries', [WebsiteInquiryController::class, 'index'])->name('website.inquiries');
            Route::post('gym/website', [WebsiteController::class, 'update'])->middleware('throttle:20,1')->name('website.update');
            Route::get('gym/website/preview', [WebsiteController::class, 'preview'])->middleware('blade-page')->name('website.preview');
            Route::post('gym/website/publish', [WebsiteController::class, 'publish'])->middleware('throttle:20,1')->name('website.publish');
            Route::get('gym/website/image', [WebsiteController::class, 'image'])->name('website.image');
        });

        Route::middleware('feature:qr-attendance')->group(function () {
            Route::get('attendance', [AttendanceController::class, 'index'])->name('attendance.index');
            Route::get('check-in', [CheckInController::class, 'index'])->name('check-in.index');
            Route::get('check-in/members', [CheckInController::class, 'search'])->middleware('throttle:attendance-search')->name('check-in.search');
            Route::post('check-in', [CheckInController::class, 'store'])->middleware('throttle:check-in')->name('check-in.store');
            Route::get('members/{member}/qr', [MemberQrController::class, 'show'])->name('member-qr.show');
            Route::post('members/{member}/qr', [MemberQrController::class, 'store'])->middleware('throttle:20,1')->name('member-qr.store');
        });

        Route::middleware('feature:members')->group(function () {
            Route::get('members/freeze-records', [MemberSubscriptionController::class, 'freezeRecords'])->name('freeze-records.index');
            Route::get('members/{member}/subscriptions', [MemberSubscriptionController::class, 'index'])->name('member-subscriptions.index');
            Route::post('members/{member}/subscriptions', [MemberSubscriptionController::class, 'store'])->name('member-subscriptions.store');
            Route::patch('members/{member}/subscriptions/{subscription}', [MemberSubscriptionController::class, 'update'])->name('member-subscriptions.update');
            Route::get('membership-plans', [MembershipPlanController::class, 'index'])->name('membership-plans.index');
            Route::patch('pricing/settings', [MembershipPlanController::class, 'settings'])->name('pricing.settings');
            Route::post('pricing/discounts', [MembershipPlanController::class, 'discount'])->name('pricing.discounts.store');
            Route::patch('pricing/discounts/{pricingDiscount}', [MembershipPlanController::class, 'discount'])->name('pricing.discounts.update');
            Route::post('pricing/quote', [MemberPurchaseController::class, 'quote'])->middleware('throttle:60,1')->name('pricing.quote');
            Route::get('members/{member}/purchases', [MemberPurchaseController::class, 'index'])->name('purchases.index');
            Route::post('members/{member}/purchases', [MemberPurchaseController::class, 'store'])->middleware('throttle:30,1')->name('purchases.store');
            Route::patch('members/{member}/purchases/{purchase}', [MemberPurchaseController::class, 'change'])->middleware('throttle:30,1')->name('purchases.change');
            Route::get('membership-plans/create', [MembershipPlanController::class, 'create'])->name('membership-plans.create');
            Route::post('membership-plans', [MembershipPlanController::class, 'store'])->name('membership-plans.store');
            Route::get('membership-plans/{membershipPlan}/edit', [MembershipPlanController::class, 'edit'])->name('membership-plans.edit');
            Route::get('membership-plans/{membershipPlan}', [MembershipPlanController::class, 'show'])->name('membership-plans.show');
            Route::patch('membership-plans/{membershipPlan}', [MembershipPlanController::class, 'update'])->name('membership-plans.update');
            Route::patch('membership-plans/{membershipPlan}/activation', [MembershipPlanController::class, 'activation'])->name('membership-plans.activation');
            Route::get('members', [MemberController::class, 'index'])->name('members.index');
            Route::get('members/create', [MemberController::class, 'create'])->name('members.create');
            Route::post('members', [MemberController::class, 'store'])->name('members.store');
            Route::get('members/{member}/edit', [MemberController::class, 'edit'])->name('members.edit');
            Route::get('members/{member}', [MemberController::class, 'show'])->name('members.show');
            Route::patch('members/{member}', [MemberController::class, 'update'])->name('members.update');
            Route::patch('members/{member}/activation', [MemberController::class, 'activation'])->name('members.activation');
        });

        Route::get('gym/branches', [BranchController::class, 'index'])->name('branches.index');
        Route::middleware('feature:payments')->group(function () {
            Route::get('payments', PaymentOverviewController::class)->name('payments.overview');
            Route::get('payments/{payment}/record', [PaymentController::class, 'show'])->name('payments.show');
            Route::get('members/{member}/payments', [PaymentController::class, 'index'])->name('payments.index');
            Route::patch('payments/{payment}/void', [PaymentController::class, 'void'])->middleware('throttle:30,1')->name('payments.void');
            Route::get('members/{member}/payments/create', [PaymentController::class, 'create'])->name('payments.create');
            Route::post('members/{member}/payments', [PaymentController::class, 'store'])->middleware('throttle:30,1')->name('payments.store');
        });
        Route::post('gym/branches', [BranchController::class, 'store'])->name('branches.store');
        Route::patch('gym/branches/{branch}', [BranchController::class, 'update'])->name('branches.update');
        Route::patch('gym/branches/{branch}/activation', [PlanSelectionController::class, 'branch'])->name('branches.activation');
        Route::put('gym/branches/selection', [PlanSelectionController::class, 'selectBranches'])->name('branches.selection');
        Route::put('gym/plan-selection/confirm', [PlanSelectionController::class, 'confirm'])->name('plan-selection.confirm');
        Route::patch('gym/staff/{member}/activation', [PlanSelectionController::class, 'staff'])->whereNumber('member')->name('staff.activation');

        Route::get('gym/settings', [GymSettingsController::class, 'edit'])->name('gym.settings.edit');
        Route::patch('gym/settings', [GymSettingsController::class, 'update'])->name('gym.settings.update');

        Route::get('gym/staff', [StaffController::class, 'index'])->name('staff.index');
        Route::post('gym/staff/invitations', [StaffController::class, 'invite'])
            ->middleware(['not-demo', 'throttle:10,1'])
            ->name('staff.invitations.store');
        Route::delete('gym/staff/invitations/{invitation}', [StaffController::class, 'revoke'])->name('staff.invitations.destroy');
        Route::patch('gym/staff/{member}', [StaffController::class, 'update'])->whereNumber('member')->name('staff.update');
    });
});

// Platform admin ("super admin") — the SaaS operator, not a gym role
Route::middleware(['auth:web', 'verified', 'can:access-platform-admin'])
    ->prefix('admin')
    ->name('platform.')
    ->group(function () {
        Route::get('gyms', [GymFeatureController::class, 'index'])->name('gyms.index');
        Route::get('gyms/{gym}/features', [GymFeatureController::class, 'edit'])->name('gyms.features.edit');
        Route::put('gyms/{gym}/features', [GymFeatureController::class, 'update'])->name('gyms.features.update');
        Route::put('gyms/{gym}/subscription', [GymSubscriptionController::class, 'update'])->name('gyms.subscription.update');

        Route::get('owner-invitations', [PlatformOwnerInvitationController::class, 'index'])->name('owner-invitations.index');
        Route::post('owner-invitations', [PlatformOwnerInvitationController::class, 'store'])->name('owner-invitations.store');
        Route::delete('owner-invitations/{invitation}', [PlatformOwnerInvitationController::class, 'destroy'])->name('owner-invitations.destroy');
        Route::delete('access-requests/{accessRequest}', [PlatformOwnerInvitationController::class, 'dismissRequest'])->name('access-requests.destroy');
    });

require __DIR__.'/settings.php';
