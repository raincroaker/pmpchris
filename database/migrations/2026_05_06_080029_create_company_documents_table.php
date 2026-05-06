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
        Schema::create('company_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('folder_id')->nullable()->constrained('company_document_folders')->nullOnDelete();

            $table->string('original_name', 255);
            $table->string('stored_name', 255);
            $table->string('disk', 50)->default('local');
            $table->string('path', 500);
            $table->string('mime_type', 150);
            $table->string('extension', 20)->nullable();
            $table->unsignedBigInteger('size_bytes');
            $table->char('checksum_sha256', 64)->nullable();

            $table->foreignId('uploaded_by_user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('access_mode', 20)->default('private');
            $table->string('status', 20)->default('approved');
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->foreignId('decided_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('decision_note')->nullable();

            $table->json('tags')->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'folder_id']);
            $table->index(['organization_id', 'status']);
            $table->index(['uploaded_by_user_id', 'status']);
            $table->index(['organization_id', 'access_mode']);
            $table->index('checksum_sha256');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('company_documents');
    }
};
