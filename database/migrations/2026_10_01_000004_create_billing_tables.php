<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('receipts', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique()->comment('UUID de la operación de cobro; evita cobros duplicados');
            $table->string('folio', 20)->unique();
            $table->foreignId('merchant_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('tianguis_id')->nullable()->constrained('tianguis')->nullOnDelete();
            $table->foreignId('route_id')->nullable()->constrained('routes')->nullOnDelete();
            $table->dateTime('issued_at')->index();
            $table->decimal('subtotal', 12, 2);
            $table->decimal('discount', 12, 2)->default(0);
            $table->decimal('surcharge', 12, 2)->default(0);
            $table->decimal('total', 12, 2);
            $table->string('payment_method', 20)->index()->comment('cash|transfer|card|other');
            $table->string('status', 20)->default('issued')->index()->comment('issued|cancelled|reprinted');
            $table->dateTime('printed_at')->nullable();
            $table->unsignedInteger('reprint_count')->default(0);
            $table->dateTime('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('cancellation_reason')->nullable();
            // Preparado para el módulo de control de caja (sin FK hasta que exista la tabla).
            $table->unsignedBigInteger('cash_register_session_id')->nullable()->index();
            $table->string('source', 20)->default('web')->comment('web|android');
            $table->string('device')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['merchant_id', 'issued_at']);
            $table->index(['user_id', 'issued_at']);
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('receipt_id')->constrained()->restrictOnDelete();
            $table->foreignId('merchant_id')->constrained()->restrictOnDelete();
            $table->foreignId('concept_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('quantity');
            $table->decimal('unit_price', 12, 2);
            $table->decimal('subtotal', 12, 2);
            $table->decimal('discount', 12, 2)->default(0);
            $table->decimal('surcharge', 12, 2)->default(0);
            $table->decimal('total', 12, 2);
            $table->string('payment_method', 20)->index();
            $table->dateTime('paid_at')->index();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('tianguis_id')->nullable()->constrained('tianguis')->nullOnDelete();
            $table->date('service_date')->nullable()->comment('Fecha del tianguis para boletos');
            $table->unsignedSmallInteger('fiscal_year')->nullable()->index();
            $table->string('status', 20)->default('applied')->index()->comment('applied|cancelled');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['concept_id', 'paid_at']);
        });

        Schema::create('payment_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('receipt_id')->constrained()->cascadeOnDelete();
            $table->string('payment_method', 20)->index()->comment('cash|transfer|card|other');
            $table->decimal('amount', 12, 2);
            $table->string('reference', 100)->nullable();
            $table->timestamps();
        });

        Schema::create('receipt_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('receipt_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payment_id')->constrained()->restrictOnDelete();
            $table->foreignId('concept_id')->constrained()->restrictOnDelete();
            $table->string('concept_code', 20);
            $table->string('description');
            $table->unsignedInteger('quantity');
            $table->decimal('unit_price', 12, 2);
            $table->decimal('amount', 12, 2);
            $table->timestamps();
        });

        Schema::create('annualities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('merchant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('concept_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('fiscal_year');
            $table->decimal('amount', 12, 2);
            $table->string('status', 20)->default('pending')->index()->comment('pending|paid');
            $table->foreignId('payment_id')->nullable()->constrained()->nullOnDelete();
            $table->dateTime('paid_at')->nullable();
            $table->timestamps();

            $table->unique(['merchant_id', 'concept_id', 'fiscal_year']);
            $table->index(['fiscal_year', 'status']);
        });

        Schema::create('receipt_reprints', function (Blueprint $table) {
            $table->id();
            $table->foreignId('receipt_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('reason');
            $table->unsignedInteger('reprint_number');
            $table->date('reprint_date');
            $table->time('reprint_time');
            $table->timestamp('created_at')->nullable();

            $table->unique(['receipt_id', 'reprint_number']);
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 20)->index()->comment('create|update|delete|charge|cancel|reprint|view|export|login|logout');
            $table->string('module', 50)->index();
            $table->nullableMorphs('auditable');
            $table->string('description')->nullable();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('device')->nullable();
            $table->timestamp('created_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('receipt_reprints');
        Schema::dropIfExists('annualities');
        Schema::dropIfExists('receipt_items');
        Schema::dropIfExists('payment_details');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('receipts');
    }
};
