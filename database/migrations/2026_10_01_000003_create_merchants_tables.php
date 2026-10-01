<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('merchants', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique()->comment('Identificador de la operación de alta (sincronización offline)');
            $table->string('folio', 20)->unique();
            $table->string('taxpayer_name', 200)->index();
            $table->string('trade_name', 200)->nullable()->index();
            $table->foreignId('business_type_id')->constrained()->restrictOnDelete();
            $table->text('business_description')->nullable();
            $table->string('phone', 20)->nullable();

            // Domicilio del contribuyente
            $table->string('address')->nullable();
            $table->string('neighborhood', 150)->nullable();
            $table->string('municipality', 150)->nullable();
            $table->string('state', 100)->nullable();

            // Ubicación física del puesto
            $table->string('location')->nullable()->comment('Dirección del puesto');
            $table->string('location_street', 150)->nullable();
            $table->string('location_number', 20)->nullable();
            $table->string('location_neighborhood', 150)->nullable();
            $table->string('location_reference')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();

            $table->foreignId('route_id')->nullable()->constrained('routes')->nullOnDelete();
            $table->foreignId('tianguis_id')->nullable()->constrained('tianguis')->nullOnDelete();
            $table->decimal('meters', 8, 2)->default(0);
            $table->string('commerce_type', 20)->index()->comment('tianguis|route|plaza|public_road');
            $table->string('status', 20)->default('active')->index()->comment('active|inactive|suspended');
            $table->date('registered_at');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['latitude', 'longitude']);
        });

        Schema::create('merchant_measurements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('merchant_id')->constrained()->cascadeOnDelete();
            $table->decimal('meters', 8, 2);
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('reason')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['merchant_id', 'end_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('merchant_measurements');
        Schema::dropIfExists('merchants');
    }
};
