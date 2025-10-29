<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('broadcast_group_recipient', function (Blueprint $table) {
            // Hapus primary key lama yang menggunakan kolom id
            DB::statement('ALTER TABLE broadcast_group_recipient DROP PRIMARY KEY');

            // Hapus kolom id
            $table->dropColumn('id');

            // Tambahkan kombinasi unik antara group_id dan recipient_id
            $table->unique(['group_id', 'recipient_id'], 'group_recipient_unique');
        });
    }

    public function down(): void
    {
        Schema::table('broadcast_group_recipient', function (Blueprint $table) {
            // Tambahkan kembali kolom id jika rollback
            $table->char('id', 36)->primary()->first();

            // Hapus unique key
            $table->dropUnique('group_recipient_unique');
        });
    }
};
