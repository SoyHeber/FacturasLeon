<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('intentos_fel', function (Blueprint $table) {
            $table->foreignId('usuario_id')->nullable()->constrained('users')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('intentos_fel', function (Blueprint $table) {
            $table->dropConstrainedForeignId('usuario_id');
        });
    }
};
