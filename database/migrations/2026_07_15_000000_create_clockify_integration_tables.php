<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clockify_connections', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('internal_user_id')->unique()->constrained('users');
            $table->text('api_token');
            $table->string('clockify_user_id');
            $table->string('clockify_workspace_id');
            $table->string('clockify_email')->nullable();
            $table->string('workspace_name')->nullable();
            $table->string('webhook_secret_hash');
            $table->string('status')->default('connected')->index();
            $table->timestamp('last_checked_at')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['clockify_workspace_id', 'clockify_user_id']);
        });

        Schema::create('integration_users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users');
            $table->foreignId('clockify_connection_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('jira_account_id')->nullable();
            $table->string('jira_email')->nullable();
            $table->string('teamboard_user_id')->nullable();
            $table->string('teamboard_email')->nullable();
            $table->string('mapping_status')->default('pending');
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();
        });

        Schema::create('clockify_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clockify_connection_id')->constrained()->cascadeOnDelete();
            $table->string('clockify_task_id');
            $table->string('clockify_project_id')->nullable();
            $table->string('clockify_task_name')->nullable();
            $table->string('jira_issue_id')->nullable();
            $table->string('jira_issue_key')->nullable();
            $table->string('mapping_source')->default('manual');
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();
            $table->unique(['clockify_connection_id', 'clockify_task_id']);
        });

        Schema::create('webhook_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clockify_connection_id')->constrained()->cascadeOnDelete();
            $table->string('event_type')->nullable();
            $table->string('external_event_id')->nullable();
            $table->string('external_object_id')->nullable();
            $table->string('payload_hash', 64);
            $table->json('payload');
            $table->json('raw_response')->nullable();
            $table->string('status')->default('pending')->index();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->timestamp('received_at');
            $table->timestamp('processed_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();
            $table->unique(['clockify_connection_id', 'payload_hash']);
        });

        Schema::create('clockify_time_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clockify_connection_id')->constrained()->cascadeOnDelete();
            $table->string('clockify_time_entry_id');
            $table->string('clockify_task_id')->nullable();
            $table->string('clockify_project_id')->nullable();
            $table->string('jira_issue_id')->nullable();
            $table->string('jira_issue_key')->nullable();
            $table->timestampTz('started_at');
            $table->timestampTz('ended_at')->nullable();
            $table->unsignedBigInteger('duration_seconds')->nullable();
            $table->text('description')->nullable();
            $table->json('raw_data');
            $table->string('source_payload_hash', 64);
            $table->string('sync_status')->default('pending_resolution')->index();
            $table->text('last_error')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
            $table->unique(['clockify_connection_id', 'clockify_time_entry_id'], 'clockify_entry_connection_unique');
        });

        Schema::create('teamboard_timelog_mappings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clockify_connection_id')->constrained()->cascadeOnDelete();
            $table->foreignId('clockify_time_entry_id')->constrained()->cascadeOnDelete();
            $table->string('teamboard_timelog_id')->nullable();
            $table->string('payload_hash', 64)->nullable();
            $table->json('raw_request')->nullable();
            $table->json('raw_response')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
            $table->unique(['clockify_connection_id', 'clockify_time_entry_id'], 'teamboard_mapping_entry_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teamboard_timelog_mappings');
        Schema::dropIfExists('clockify_time_entries');
        Schema::dropIfExists('webhook_events');
        Schema::dropIfExists('clockify_tasks');
        Schema::dropIfExists('integration_users');
        Schema::dropIfExists('clockify_connections');
    }
};
