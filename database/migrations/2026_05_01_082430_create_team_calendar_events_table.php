<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('team_calendar_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('root_unit_id')->constrained('organizational_units')->cascadeOnDelete();
            $table->foreignId('unit_id')->constrained('organizational_units')->cascadeOnDelete();
            $table->foreignId('category_id')->constrained('calendar_event_categories')->restrictOnDelete();
            $table->string('title', 160);
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->boolean('is_all_day')->default(false);
            $table->string('location', 255)->nullable();
            $table->text('details')->nullable();
            $table->json('recurrence')->nullable();
            $table->foreignId('set_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('last_edited_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'root_unit_id', 'unit_id', 'starts_at'], 'team_events_org_root_unit_start_idx');
            $table->index(['organization_id', 'category_id'], 'team_events_org_category_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('team_calendar_events');
    }
};
