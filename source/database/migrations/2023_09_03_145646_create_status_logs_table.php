<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('status_ocorrencia')) {
            Schema::dropIfExists('status');

            Schema::table('status_ocorrencia', function (Blueprint $table) {
                $table->renameColumn('id_ocorrencia', 'order_id');
                $table->renameColumn('id_status', 'status');
                $table->renameColumn('mensagem', 'message');
                $table->renameColumn('id_usuario', 'user_id');
                $table->renameColumn('data_mudanca', 'created_at');
                $table->timestamp('updated_at')->nullable();
            });
            Schema::table('status_ocorrencia', function (Blueprint $table) {
                $table->string('status', 255)->change();
            });
            Schema::rename('status_ocorrencia', 'order_status_logs');
            // // Renomear a coluna existente para uma coluna temporária
            Schema::table('order_status_logs', function (Blueprint $table) {
                $table->renameColumn('status', 'status_temp');
            });

            // Criar uma nova coluna com o tipo enum
            Schema::table('order_status_logs', function (Blueprint $table) {
                $table->enum('status', ['opened', 'reopened', 'pending customer response', 'reserved', 'closed', 'pending it resource', 'canceled', 'committed', 'in progress'])->nullable();
            });

            // Preencher os valores da nova coluna com base na coluna temporária
            DB::table('order_status_logs')->update([
                'status' => DB::raw("CASE
                WHEN status_temp = '2' THEN 'opened'
                WHEN status_temp = '7' THEN 'reopened'
                WHEN status_temp = '8' THEN 'opened'
                WHEN status_temp = '10' THEN 'pending customer response'
                WHEN status_temp = '1' THEN 'reserved'
                WHEN status_temp = '4' THEN 'closed'
                WHEN status_temp = '9' THEN 'pending it resource'
                WHEN status_temp = '6' THEN 'canceled'
                WHEN status_temp = '5' THEN 'committed'
                WHEN status_temp = '3' THEN 'in progress'
            END"),
            ]);

            // Remover a coluna temporária
            Schema::table('order_status_logs', function (Blueprint $table) {
                $table->dropColumn('status_temp');
            });
        } else {
            Schema::create('status_logs', function (Blueprint $table) {
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
        Schema::dropIfExists('status_logs');
    }
};
