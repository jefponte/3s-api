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
        if (Schema::hasTable('ocorrencia')) {

            Schema::table('ocorrencia', function (Blueprint $table) {
                $table->renameColumn('id_area_responsavel', 'division_id');
                $table->renameColumn('id_servico', 'service_id');
                $table->renameColumn('id_usuario_cliente', 'customer_user_id');
                $table->renameColumn('id_usuario_atendente', 'provider_user_id');
                $table->dropColumn('id_usuario_indicado');
                $table->renameColumn('id_local', 'division_sig_id');
                $table->dropColumn('prioridade');
                $table->string('priority')->nullable();
                $table->renameColumn('descricao', 'description');
                $table->renameColumn('patrimonio', 'tag');
                $table->renameColumn('local', 'division_sig');
                $table->renameColumn('solucao', 'solution');
                $table->renameColumn('data_abertura', 'created_at');
                $table->renameColumn('data_atendimento', 'service_at');
                $table->renameColumn('data_fechamento', 'finished_at');
                $table->renameColumn('data_fechamento_confirmado', 'committed_at');
                $table->renameColumn('avaliacao', 'rating');
                $table->renameColumn('ramal', 'phone_number');
                $table->renameColumn('anexo', 'attachment');
                $table->renameColumn('local_sala', 'place');
                $table->timestamp('updated_at')->nullable();

            });
            Schema::table('ocorrencia', function (Blueprint $table) {
                $table->unsignedBigInteger('customer_user_id')->nullable()->change();
                $table->unsignedBigInteger('provider_user_id')->nullable()->change();
                $table->string('status', 255)->nullable()->change();
                $table->integer('division_sig_id')->nullable()->change();
                $table->unsignedBigInteger('division_id')->nullable()->change();
                $table->unsignedBigInteger('service_id')->nullable()->change();
            });

            Schema::rename('ocorrencia', 'orders');
            Schema::table('orders', function (Blueprint $table) {
                $table->renameColumn('status', 'status_temp');
            });

            Schema::table('orders', function (Blueprint $table) {
                $table->enum('status', ['opened', 'reopened', 'pending customer response', 'reserved', 'closed', 'pending resource', 'canceled', 'committed', 'in progress'])->nullable();
            });

            DB::table('orders')->update([
                'status' => DB::raw("CASE
                    WHEN status_temp = 'a' THEN 'opened'
                    WHEN status_temp = 'r' THEN 'reopened'
                    WHEN status_temp = 'c' THEN 'opened'
                    WHEN status_temp = 'd' THEN 'pending customer response'
                    WHEN status_temp = 'b' THEN 'reserved'
                    WHEN status_temp = 'g' THEN 'committed'
                    WHEN status_temp = 'i' THEN 'pending resource'
                    WHEN status_temp = 'h' THEN 'canceled'
                    WHEN status_temp = 'f' THEN 'closed'
                    WHEN status_temp = 'e' THEN 'in progress'
                END"),
            ]);
            Schema::table('orders', function (Blueprint $table) {
                $table->dropColumn('status_temp');
            });

        } else {
            Schema::create('orders', function (Blueprint $table) {
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
        Schema::dropIfExists('orders');
    }
};
