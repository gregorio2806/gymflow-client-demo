<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_exports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gym_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('requested_role', 30);
            $table->date('from');
            $table->date('through');
            $table->uuid('request_key');
            $table->string('status', 20)->default('queued');
            $table->mediumText('csv')->nullable();
            $table->timestamp('expires_at');
            $table->timestamps();
            $table->unique(['gym_id', 'request_key']);
            $table->index(['gym_id', 'user_id', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_exports');
    }
};
