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
        if (Schema::hasTable('area_responsavel')) {
            Schema::table('area_responsavel', function (Blueprint $table) {
                $table->renameColumn('nome', 'name');
                $table->renameColumn('descricao', 'description');
                $table->timestamps();
            });
            Schema::rename('area_responsavel', 'divisions');
        } else {
            Schema::create('divisions', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('description');
                $table->string('email');
                $table->timestamps();
            });
        }

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('divisions');
    }
};
