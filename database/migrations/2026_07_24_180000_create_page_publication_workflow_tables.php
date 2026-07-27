<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('page_publication_states', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->ulid('page_id')->unique();
            $table->ulid('candidate_revision_id')->nullable()->index();
            $table->string('candidate_state', 32)->nullable()->index();
            $table->ulid('current_public_revision_id')->nullable()->index();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('scheduled_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('scheduled_for')->nullable()->index();
            $table->unsignedBigInteger('state_version')->default(0);
            $table->timestamp('last_transition_at')->nullable();
            $table->timestamps();

            $table->foreign('page_id')->references('id')->on('pages')->restrictOnDelete();
            $table->foreign('candidate_revision_id')->references('id')->on('content_revisions')->restrictOnDelete();
            $table->foreign('current_public_revision_id')->references('id')->on('content_revisions')->restrictOnDelete();
        });

        Schema::create('page_publication_transitions', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->ulid('page_id');
            $table->ulid('publication_state_id');
            $table->string('from_state', 64)->nullable();
            $table->string('to_state', 64);
            $table->ulid('revision_id')->nullable();
            $table->foreignId('actor_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->text('note')->nullable();
            $table->text('reason')->nullable();
            $table->timestamp('scheduled_for')->nullable();
            $table->string('job_identity', 128)->nullable()->unique();
            $table->ulid('correlation_id');
            $table->timestamp('occurred_at');

            $table->foreign('page_id')->references('id')->on('pages')->restrictOnDelete();
            $table->foreign('publication_state_id')->references('id')->on('page_publication_states')->restrictOnDelete();
            $table->foreign('revision_id')->references('id')->on('content_revisions')->restrictOnDelete();
            $table->index(['page_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('page_publication_transitions');
        Schema::dropIfExists('page_publication_states');
    }
};
