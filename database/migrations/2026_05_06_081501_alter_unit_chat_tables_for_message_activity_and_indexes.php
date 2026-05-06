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
        Schema::table('unit_chat_rooms', function (Blueprint $table) {
            $table->foreignId('last_message_id')
                ->nullable()
                ->after('organizational_unit_id')
                ->constrained('unit_chat_messages')
                ->nullOnDelete();
            $table->timestamp('last_message_at')
                ->nullable()
                ->after('last_message_id')
                ->index();
        });

        Schema::table('unit_chat_messages', function (Blueprint $table) {
            $table->index(['unit_chat_room_id', 'id'], 'unit_chat_messages_room_id_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('unit_chat_messages', function (Blueprint $table) {
            $table->dropIndex('unit_chat_messages_room_id_idx');
        });

        Schema::table('unit_chat_rooms', function (Blueprint $table) {
            $table->dropConstrainedForeignId('last_message_id');
            $table->dropColumn('last_message_at');
        });
    }
};
