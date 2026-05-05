<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Employee contacts: many rows per employee (UI: add-contact repeater).
     *
     * category personal|emergency drives which optional inputs are shown.
     * personal: type is typically mobile|home|work; contact_person and relationship stay null.
     * emergency: contact_person and relationship used; type optional (mobile or null as channel hint).
     * is_primary: for personal, primary number among personal contacts; for emergency, primary emergency
     * when multiple exist. Enforce at most one primary per slice in application logic, not here.
     */
    public function up(): void
    {
        Schema::create('employee_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')
                ->comment('FK to employees; removed when employee is deleted.')
                ->constrained()
                ->cascadeOnDelete();
            $table->string('category', 20)
                ->comment('personal|emergency. Drives conditional UI fields and allowed type values.');
            $table->string('type', 20)
                ->nullable()
                ->comment('personal: mobile|home|work. emergency: optional mobile or null.');
            $table->string('contact_person', 150)
                ->nullable()
                ->comment('Emergency: contact person name. Null when category is personal.');
            $table->string('relationship', 100)
                ->nullable()
                ->comment('Emergency: relationship to employee. Null when category is personal.');
            $table->string('contact_number', 50)
                ->comment('Phone or primary contact value for this row.');
            $table->string('email', 255)
                ->nullable()
                ->comment('Optional email for this contact row.');
            $table->boolean('is_primary')
                ->default(false)
                ->comment('personal: primary number. emergency: primary emergency among multiples.');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['employee_id', 'category']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employee_contacts');
    }
};
