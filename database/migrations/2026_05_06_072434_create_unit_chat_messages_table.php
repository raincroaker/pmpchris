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
        Schema::create('unit_chat_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unit_chat_room_id')
                ->constrained('unit_chat_rooms')
                ->cascadeOnDelete();
            $table->foreignId('sender_user_id')
                ->constrained('users')
                ->cascadeOnDelete();
            $table->text('body');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('unit_chat_messages');
    }
};
