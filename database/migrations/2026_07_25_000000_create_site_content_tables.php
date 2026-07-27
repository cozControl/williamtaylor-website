<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_contents', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('type', 48);
            $table->string('key', 120);
            $table->string('locale', 10)->default('en');
            $table->string('title', 160);
            $table->foreignUlid('current_draft_revision_id')->nullable()->constrained('content_revisions')->nullOnDelete();
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('updated_by')->constrained('users');
            $table->foreignId('archived_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('archived_at')->nullable();
            $table->unsignedBigInteger('lock_version')->default(1);
            $table->timestamps();
            $table->unique(['type', 'key', 'locale']);
            $table->index(['type', 'locale', 'archived_at']);
        });

        Schema::create('site_content_publication_states', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('site_content_id')->unique()->constrained('site_contents')->cascadeOnDelete();
            $table->foreignUlid('candidate_revision_id')->nullable()->constrained('content_revisions')->nullOnDelete();
            $table->string('candidate_state', 32)->nullable();
            $table->foreignUlid('current_public_revision_id')->nullable()->constrained('content_revisions', 'id', 'sc_states_public_revision_fk')->nullOnDelete();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('approved_at')->nullable();
            $table->foreignId('scheduled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('scheduled_for')->nullable();
            $table->unsignedBigInteger('state_version')->default(0);
            $table->timestampTz('last_transition_at')->nullable();
            $table->timestamps();
        });

        Schema::create('site_content_publication_transitions', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('site_content_id')->constrained('site_contents')->cascadeOnDelete();
            $table->foreignUlid('publication_state_id')->constrained('site_content_publication_states', 'id', 'sc_transitions_state_fk')->cascadeOnDelete();
            $table->string('from_state', 32)->nullable();
            $table->string('to_state', 32);
            $table->foreignUlid('revision_id')->constrained('content_revisions');
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('note')->nullable();
            $table->text('reason')->nullable();
            $table->timestampTz('scheduled_for')->nullable();
            $table->string('job_identity')->nullable()->unique();
            $table->ulid('correlation_id');
            $table->timestampTz('occurred_at');
            $table->index(['site_content_id', 'occurred_at'], 'sc_transitions_content_time_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_content_publication_transitions');
        Schema::dropIfExists('site_content_publication_states');
        Schema::dropIfExists('site_contents');
    }
};
