<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('compras', function (Blueprint $table) {
            $table->foreignId('tipo_documento_id')
                ->nullable()
                ->index()
                ->constrained('tipos_documento')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('compras', function (Blueprint $table) {
            $table->dropForeign(['tipo_documento_id']);
            $table->dropIndex(['tipo_documento_id']);
            $table->dropColumn('tipo_documento_id');
        });
    }
};
