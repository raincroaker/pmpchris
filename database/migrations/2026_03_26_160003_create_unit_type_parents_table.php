<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('unit_type_parents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_unit_type_id')->constrained('unit_types')->cascadeOnDelete();
            $table->foreignId('child_unit_type_id')->constrained('unit_types')->cascadeOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['parent_unit_type_id', 'child_unit_type_id']);
            $table->index('parent_unit_type_id');
            $table->index('child_unit_type_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('unit_type_parents');
    }
};
