<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Cortes de caja: cada recaudador abre su corte (inicio de cobro con fondo
 * inicial) y lo cierra con el arqueo del efectivo contado.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_register_sessions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique()->comment('Idempotencia de la apertura desde la app');
            $table->string('folio', 20)->unique();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('status', 20)->default('open')->index()->comment('open|closed');

            $table->dateTime('opened_at');
            $table->decimal('opening_amount', 12, 2)->default(0)->comment('Fondo inicial de caja');
            $table->text('opening_notes')->nullable();
            $table->string('source', 20)->default('web')->comment('web|android');
            $table->string('device')->nullable();

            $table->dateTime('closed_at')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('total_collected', 12, 2)->nullable()->comment('Cobrado según el sistema (sin cancelados)');
            $table->decimal('expected_cash', 12, 2)->nullable()->comment('Fondo + cobros en efectivo');
            $table->decimal('counted_cash', 12, 2)->nullable()->comment('Efectivo contado en el arqueo');
            $table->decimal('difference', 12, 2)->nullable()->comment('Contado - esperado (+ sobrante, - faltante)');
            $table->json('summary')->nullable()->comment('Totales congelados al cerrar');
            $table->text('closing_notes')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index('opened_at');
        });

        Schema::table('receipts', function (Blueprint $table) {
            $table->foreign('cash_register_session_id')->references('id')->on('cash_register_sessions')->restrictOnDelete();
        });

        DB::table('folio_sequences')->insertOrIgnore([
            'name' => 'cash_session',
            'prefix' => 'COR-',
            'padding' => 6,
            'last_number' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::table('receipts', function (Blueprint $table) {
            $table->dropForeign(['cash_register_session_id']);
        });

        Schema::dropIfExists('cash_register_sessions');
        DB::table('folio_sequences')->where('name', 'cash_session')->delete();
    }
};
