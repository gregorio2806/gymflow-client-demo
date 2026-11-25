<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gym_websites', fn (Blueprint $table) => $table->boolean('contact_form_enabled')->default(false));
        Schema::create('website_inquiries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gym_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('email');
            $table->text('message');
            $table->string('request_hash', 64);
            $table->string('content_hash', 64);
            $table->string('privacy_version', 20);
            $table->string('privacy_revision', 64);
            $table->timestamp('consented_at');
            $table->timestamps();
            $table->unique(['gym_id', 'request_hash']);
            $table->index(['gym_id', 'created_at']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('website_inquiries');
        Schema::table('gym_websites', fn (Blueprint $table) => $table->dropColumn('contact_form_enabled'));
    }
};
