<?php

use App\Http\Middleware\HandleInertiaRequests;
use App\Models\AttendanceRecord;
use App\Models\Gym;
use App\Models\Member;
use App\Support\LegalDocuments;
use App\Tenancy\CurrentGym;
use Database\Seeders\LoadTestingSeeder;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (! app()->environment('local')) {
    throw new RuntimeException('Local testing only.');
}
$gym = Gym::where('slug', LoadTestingSeeder::SLUG)->firstOrFail();
app(CurrentGym::class)->runAs($gym, function () use ($gym) {
    echo json_encode([
        'slug' => $gym->slug,
        'branch' => $gym->mainBranch()->id,
        'members' => Member::where('email', 'like', 'member00%@loadtest.invalid')->where('is_active', true)->orderBy('email')->limit(12)->pluck('id'),
        'member_count' => Member::count(),
        'attendance_count' => AttendanceRecord::count(),
        'date' => now()->setTimezone($gym->timezone)->toDateString(),
        'version' => app(HandleInertiaRequests::class)->version(request()),
        'policy_version' => LegalDocuments::bundle()['version'],
    ], JSON_THROW_ON_ERROR);
});
