<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Drops tables for removed HRIS modules: unit chat, employee attendance days, company documents.
     */
    public function up(): void
    {
        Schema::withoutForeignKeyConstraints(function (): void {
            Schema::dropIfExists('unit_chat_reads');
            Schema::dropIfExists('unit_chat_messages');
            Schema::dropIfExists('unit_chat_rooms');

            Schema::dropIfExists('employee_attendance_segments');
            Schema::dropIfExists('employee_attendance_days');

            Schema::dropIfExists('company_document_approval_audits');
            Schema::dropIfExists('company_documents');
            Schema::dropIfExists('company_document_folders');
        });
    }

    public function down(): void
    {
        // Irreversible data drop; restore from backup or re-run original migrations if needed.
    }
};
