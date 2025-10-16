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
        Schema::create('broadcast_unsubscribe_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('recipient_id');
            $table->string('reason')->nullable(); // contoh: 'user_clicked_link', 'manual_admin'
            $table->timestamp('unsubscribed_at');
            $table->timestamps();

            $table->foreign('recipient_id')
                ->references('id')
                ->on('broadcast_recipients')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('unsubscribe_logs');
    }
};
