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
        Schema::create('company_document_approval_audits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_document_id')->constrained('company_documents')->cascadeOnDelete();
            $table->string('action', 30);
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('note')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(
                ['company_document_id', 'created_at'],
                'company_doc_audits_doc_created_idx'
            );
            $table->index(
                ['actor_user_id', 'created_at'],
                'company_doc_audits_actor_created_idx'
            );
            $table->index('action', 'company_doc_audits_action_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('company_document_approval_audits');
    }
};
