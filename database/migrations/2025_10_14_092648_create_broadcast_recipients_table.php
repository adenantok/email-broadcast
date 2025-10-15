<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('broadcast_recipients', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('nama_perusahaan', 255);
            $table->string('pic', 255)->nullable();
            $table->string('email', 255)->unique();
            $table->boolean('is_subscribed')->default(true);
            $table->timestamp('last_sent_at')->nullable();
            $table->integer('sent_count')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('broadcast_recipients');
    }
};
