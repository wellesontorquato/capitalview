<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            // CONTATO / IDENTIFICAÇÃO
            $table->string('email', 190)->nullable();
            $table->string('cpf', 14)->nullable()->after('email'); // pode armazenar com máscara 000.000.000-00
            $table->string('rg', 20)->nullable()->after('cpf');

            // ENDEREÇO
            $table->string('cep', 9)->nullable()->after('rg'); // 00000-000
            $table->string('logradouro', 120)->nullable()->after('cep');
            $table->string('numero', 20)->nullable()->after('logradouro');
            $table->string('complemento', 120)->nullable()->after('numero');
            $table->string('bairro', 80)->nullable()->after('complemento');
            $table->string('cidade', 80)->nullable()->after('bairro');
            $table->char('uf', 2)->nullable()->after('cidade');
        });
    }

    public function down(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            $table->dropColumn([
                'apelido',
                'whatsapp',
                'email',
                'cpf',
                'rg',
                'cep',
                'logradouro',
                'numero',
                'complemento',
                'bairro',
                'cidade',
                'uf',
                'observacoes',
            ]);
        });
    }
};
