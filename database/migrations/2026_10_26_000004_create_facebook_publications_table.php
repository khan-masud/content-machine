<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * One attempt to put a post on the workspace's Facebook Page, scheduled by
 * Content Machine's own scheduler (facebook:publish-due). A post has at most
 * one active (scheduled / queued / publishing / uncertain) publication.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('facebook_publications', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->foreignId('telegram_post_request_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('page_id', 64);
            $table->string('state', 16);
            $table->timestampTz('scheduled_for')->nullable();
            $table->string('lease_id', 64)->nullable();
            $table->timestampTz('claimed_at')->nullable();
            $table->string('facebook_post_id', 128)->nullable();
            $table->string('permalink', 500)->nullable();
            $table->text('error')->nullable();
            $table->text('comment_error')->nullable();
            $table->timestampTz('published_at')->nullable();
            $table->timestamps();

            $table->index(['state', 'scheduled_for']);
            $table->index(['post_id', 'state']);
        });

        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement("create unique index facebook_publications_one_active_per_post on facebook_publications (post_id) where state in ('scheduled', 'queued', 'publishing', 'uncertain')");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('facebook_publications');
    }
};
