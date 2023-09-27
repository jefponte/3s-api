<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('mensagem_forum')) {
            Schema::table('mensagem_forum', function (Blueprint $table) {
                $table->renameColumn('id_ocorrencia', 'order_id');
                $table->renameColumn('tipo', 'type');
                $table->renameColumn('mensagem', 'message');
                $table->renameColumn('id_usuario', 'user_id');
                $table->renameColumn('data_envio', 'created_at');
                $table->timestamp('updated_at')->nullable();
            });
            Schema::rename('mensagem_forum', 'order_messages');

            Schema::table('order_messages', function (Blueprint $table) {
                $table->string('type', 255)->change();
            });
            Schema::table('order_messages', function (Blueprint $table) {
                $table->renameColumn('type', 'type_temp');
            });

            Schema::table('order_messages', function (Blueprint $table) {
                $table->string('type', 255)->nullable();
            });

            DB::table('order_messages')->update([
                'type' => DB::raw("CASE
                    WHEN type_temp = '1' THEN 'text'
                    WHEN type_temp = '2' THEN 'file'
                END"),
            ]);

            Schema::table('order_messages', function (Blueprint $table) {
                $table->dropColumn('type_temp');
            });
        } else {
            Schema::create('order_messages', function (Blueprint $table) {
                $table->id();
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_messages');
    }
};
