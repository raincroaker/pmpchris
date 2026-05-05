<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organizational_units', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('code', 100);
            $table->string('name', 100);
            $table->foreignId('unit_type_id')->constrained('unit_types');
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('organizational_units')->cascadeOnDelete();
            $table->foreignId('area_id')->nullable()->constrained('areas')->nullOnDelete();
            $table->text('address')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['code', 'organization_id']);
            $table->index('parent_id');
            $table->index('unit_type_id');
            $table->index('organization_id');
            $table->index('area_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organizational_units');
    }
};
