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
        Schema::create('broadcast_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('recipient_id'); // relasi ke broadcast_recipients.id
            $table->string('status'); // 'success', 'invalid_email', 'invalid_domain', 'smtp_error', dll
            $table->text('message')->nullable(); // pesan error lengkap
            $table->timestamp('sent_at')->nullable();
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
        Schema::dropIfExists('broadcast_logs');
    }
};
