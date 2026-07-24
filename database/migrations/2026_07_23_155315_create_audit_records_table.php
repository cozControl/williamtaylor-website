<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_records', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->unsignedBigInteger('actor_user_id')->nullable()->index();
            $table->json('effective_roles');
            $table->json('effective_permissions');
            $table->string('action', 120)->index();
            $table->string('resource_type', 160);
            $table->string('resource_identifier', 64);
            $table->json('before_summary')->nullable();
            $table->json('after_summary')->nullable();
            $table->string('permission', 120)->nullable();
            $table->text('reason')->nullable();
            $table->string('request_id', 64)->nullable()->index();
            $table->string('session_id', 128)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 512)->nullable();
            $table->string('job_name', 255)->nullable();
            $table->ulid('correlation_id')->index();
            $table->timestamp('created_at', 6)->useCurrent();

            $table->index(['resource_type', 'resource_identifier']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_records');
    }
};
