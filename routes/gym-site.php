<?php

use App\Http\Controllers\GymSite\HomeController;
use App\Http\Controllers\GymSite\ImageController;
use App\Http\Controllers\GymSite\InquiryController;
use App\Http\Controllers\GymSite\SitemapController;
use Illuminate\Support\Facades\Route;

Route::domain('{gym}.'.config('tenancy.platform_domain'))
    ->where(['gym' => '[a-z0-9][a-z0-9-]*'])
    ->middleware(['gym-subdomain', 'feature:website', 'blade-page'])
    ->name('gym-site.')
    ->group(function () {
        Route::get('/', HomeController::class)->name('home');
        Route::get('/sitemap.xml', SitemapController::class)->name('sitemap');
        Route::get('/website-image', ImageController::class)->name('image');
        Route::post('/contact', [InquiryController::class, 'store'])->middleware('throttle:website-inquiry')->name('inquiries.store');
        foreach (['about', 'plans', 'facilities', 'announcements', 'contact'] as $page) {
            Route::get('/'.$page, HomeController::class)->name($page);
        }
    });
