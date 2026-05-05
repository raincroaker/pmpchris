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
        Schema::create('employee_affiliations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_employment_id')->constrained('employee_employments')->cascadeOnDelete();

            /*
             * organization_id: required — which legal entity / tenant this affiliation belongs to.
             * root_unit_id: optional — when set, membership under that root organizational unit (e.g. branch).
             * When null, the employee is affiliated at organization scope only (no specific root branch).
             * Application rule when root_unit_id is set: that unit's organization_id must match organization_id
             * (enforce in Form Requests / services; not expressed as a DB constraint here).
             */
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('root_unit_id')->nullable()->constrained('organizational_units')->cascadeOnDelete();

            $table->boolean('is_primary')->default(false);
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['employee_id', 'start_date']);
            $table->index(['employee_employment_id', 'start_date'], 'emp_aff_emp_employment_start_idx');
            $table->index('organization_id');
            $table->index(['employee_id', 'organization_id']);
            $table->index(['employee_employment_id', 'organization_id'], 'emp_aff_emp_employment_org_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employee_affiliations');
    }
};
