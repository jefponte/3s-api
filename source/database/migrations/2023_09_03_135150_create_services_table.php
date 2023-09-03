<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
//php artisan make:migration create_divisions_table
//php artisan make:migration create_services_table
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('servico')) {
            Schema::table('servico', function (Blueprint $table) {

                $table->renameColumn('nome', 'name');
                $table->renameColumn('descricao', 'description');
                $table->renameColumn('tempo_sla', 'sla');
                $table->renameColumn('id_area_responsavel', 'division_id');
                $table->renameColumn('visao', 'role');
                $table->json('details')->nullable();
                $table->timestamps();
            });
            DB::table('servico')
                ->update([
                    'details' => DB::raw('
                    jsonb_build_object(\'service_group\',
                        CASE
                            WHEN id_grupo_servico = 0 THEN \'Indefinido\'
                            WHEN id_grupo_servico = 1 THEN \'SUPORTE A ACESSO AO DOMÍNIO INSTITUCIONAL\'
                            WHEN id_grupo_servico = 2 THEN \'SUPORTE A BANCO DE DADOS\'
                            WHEN id_grupo_servico = 3 THEN \'SUPORTE A E-MAIL INSTITUCIONAL\'
                            WHEN id_grupo_servico = 4 THEN \'SUPORTE A EQUIPAMENTO E PERIFÉRICO COMPUTACIONAIS\'
                            WHEN id_grupo_servico = 5 THEN \'SUPORTE À IMPRESSÃO CORPORATIVA\'
                            WHEN id_grupo_servico = 6 THEN \'SUPORTE A SOFTWARE E RECURSO INTEGRADO\'
                            WHEN id_grupo_servico = 7 THEN \'SUPORTE AO USUÁRIO(A) DE TI\'
                            WHEN id_grupo_servico = 8 THEN \'SUPORTE A SISTEMA DE APOIO À GESTÃO\'
                            WHEN id_grupo_servico = 9 THEN \'Serviços - Governança de TIC\'
                            ELSE \'Indefinido\' -- Adicione um valor padrão caso nenhum dos casos se aplique
                        END
                    ) ||
                    jsonb_build_object(\'type\',
                        CASE
                            WHEN id_tipo_atividade = 77 THEN \'serviço\'
                            WHEN id_tipo_atividade = 78 THEN \'incidente\'
                            WHEN id_tipo_atividade = 79 THEN \'dúvida\'
                            WHEN id_tipo_atividade = 80 THEN \'projeto\'
                            WHEN id_tipo_atividade = 81 THEN \'tarefa\'
                            ELSE \'Indefinido\' -- Adicione um valor padrão caso nenhum dos casos se aplique
                        END
                    )
                ')
                ]);

            Schema::table('servico', function (Blueprint $table) {
                $table->dropColumn(['id_grupo_servico', 'id_tipo_atividade']);
                $table->string('role')->change()->nullable();
            });
            DB::table('servico')
                ->whereIn('role', ['0', '1', '2'])
                ->update([
                    'role' => DB::raw("CASE
                WHEN role = '0' THEN 'disabled'
                WHEN role = '1' THEN 'customer'
                WHEN role = '2' THEN 'provider'
            END"),
                ]);
            Schema::rename('servico', 'services');
        } else {
            Schema::create('services', function (Blueprint $table) {
                $table->id();
                $table->text('name');
                $table->text('description');
                $table->integer('sla');
                $table->string('role', 255);
                $table->unsignedBigInteger('division_id');
                $table->json('details')->nullable();
                $table->timestamps();
                $table->foreign('division_id')->references('id')->on('area_responsavel');
                $table->primary('id');
            });
        }
        Schema::dropIfExists('grupo_servico');
        Schema::dropIfExists('tipo_atividade');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('services');
    }
};
