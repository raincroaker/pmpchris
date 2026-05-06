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
        Schema::create('unit_chat_reads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unit_chat_room_id')
                ->constrained('unit_chat_rooms')
                ->cascadeOnDelete();
            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();
            $table->foreignId('last_read_message_id')
                ->nullable()
                ->constrained('unit_chat_messages')
                ->nullOnDelete();
            $table->timestamp('last_read_at')->nullable();
            $table->timestamps();

            $table->unique(['unit_chat_room_id', 'user_id'], 'unit_chat_reads_room_user_unique');
            $table->index('user_id');
            $table->index('last_read_message_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('unit_chat_reads');
    }
};
