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
        if (Schema::hasTable('usuario')) {

            Schema::table('usuario', function (Blueprint $table) {

                $table->unsignedBigInteger('id')->change();
                $table->renameColumn('senha', 'password');
                $table->renameColumn('nome', 'name');
                $table->renameColumn('id_setor', 'division_id');
                $table->string('division_sig')->nullable();
                $table->integer('division_sig_id')->nullable();
                $table->renameColumn('nivel', 'role');
                $table->timestamp('email_verified_at')->nullable();
                $table->string('remember_token', 100)->nullable();
                $table->timestamps();
            });

            Schema::table('usuario', function (Blueprint $table) {
                $table->string('name', 255)->change()->nullable();
                $table->string('password', 255)->change()->nullable();
                $table->string('login', 255)->change()->nullable();
                $table->string('email', 255)->change()->nullable();
                $table->unsignedBigInteger('division_id')->change()->nullable();
                $table->string('role')->change()->nullable();
            });
            Schema::rename('usuario', 'users');
            DB::table('users')
                ->whereIn('role', ['a', 'c', 't'])
                ->update([
                    'role' => DB::raw("CASE
                    WHEN role = 'a' THEN 'administrator'
                    WHEN role = 'c' THEN 'customer'
                    WHEN role = 't' THEN 'provider'
                END"),
                ]);
        } else {
            Schema::create('users', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('email')->unique();
                $table->string('login')->nullable();
                $table->string('password')->nullable();
                $table->string('role')->nullable();
                $table->unsignedBigInteger('division_id')->nullable();

                $table->string('division_sig')->nullable();
                $table->integer('division_sig_id')->nullable();

                $table->timestamp('email_verified_at')->nullable();
                $table->rememberToken();
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
