<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('broadcast_group_recipient', function (Blueprint $table) {
            $table->uuid('group_id');
            $table->uuid('recipient_id');
            $table->timestamps();

            $table->foreign('group_id')
                ->references('id')
                ->on('broadcast_groups')
                ->onDelete('cascade');

            $table->foreign('recipient_id')
                ->references('id')
                ->on('broadcast_recipients')
                ->onDelete('cascade');

            // Prevent duplicate entries
            $table->unique(['group_id', 'recipient_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('broadcast_group_recipient');
    }
};