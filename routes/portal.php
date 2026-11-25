<?php

use App\Http\Controllers\Portal\AccountAgreementController;
use App\Http\Controllers\Portal\AuthController;
use App\Http\Controllers\Portal\CalorieProfileController;
use App\Http\Controllers\Portal\MembershipCheckController;
use App\Http\Controllers\Portal\MembershipController;
use App\Http\Controllers\Portal\WorkoutController;
use Illuminate\Support\Facades\Route;

Route::domain('{gym}.'.config('tenancy.platform_domain'))->where(['gym' => '[a-z0-9][a-z0-9-]*'])
    ->prefix('portal')->name('portal.')->middleware(['gym-subdomain', 'feature:member-portal', 'portal-privacy'])->group(function () {
        Route::get('check-membership', [MembershipCheckController::class, 'create'])->name('check');
        Route::post('check-membership', [MembershipCheckController::class, 'store'])->middleware('throttle:portal-resolution')->name('check-status');
        Route::post('check-membership/attendance-qr', [MembershipCheckController::class, 'attendanceQr'])->middleware(['feature:qr-attendance', 'throttle:portal-resolution'])->name('check-attendance-qr');
        Route::middleware('feature:member-auth')->group(function () {
            Route::get('access', [AuthController::class, 'access'])->name('access');
            Route::post('resolve', [AuthController::class, 'resolve'])->middleware('throttle:portal-resolution')->name('resolve');
            Route::get('login', [AuthController::class, 'create'])->name('login');
            Route::post('login', [AuthController::class, 'store'])->middleware('throttle:portal-login')->name('authenticate');
            Route::post('logout', [AuthController::class, 'destroy'])->name('logout');
            Route::get('agreement', [AccountAgreementController::class, 'show'])->middleware('portal-member')->name('agreement');
            Route::post('agreement', [AccountAgreementController::class, 'store'])->middleware(['portal-member', 'throttle:20,1'])->name('accept-agreement');
            Route::get('/', MembershipController::class)->middleware('portal-member')->name('home');
            Route::get('workouts', WorkoutController::class)->middleware(['portal-member', 'feature:workouts'])->name('workouts');
            Route::get('calorie-helper', [CalorieProfileController::class, 'show'])->middleware('portal-member')->name('calorie');
            Route::post('calorie-helper', [CalorieProfileController::class, 'store'])->middleware(['portal-member', 'feature:calorie-helper', 'throttle:20,1'])->name('calorie-save');
            Route::delete('calorie-helper', [CalorieProfileController::class, 'destroy'])->middleware(['portal-member', 'throttle:20,1'])->name('calorie-delete');
        });
    });
