<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_types', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('tianguis', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150)->unique();
            $table->text('description')->nullable();
            $table->string('location')->nullable();
            $table->string('neighborhood', 150)->nullable();
            $table->string('municipality', 150)->nullable();
            $table->string('operating_day', 20)->nullable()->comment('lunes..domingo');
            $table->string('schedule', 100)->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('routes', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150)->unique();
            $table->text('description')->nullable();
            $table->string('zone', 150)->nullable();
            $table->text('itinerary')->nullable()->comment('Recorrido');
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('concepts', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name', 150);
            $table->text('description')->nullable();
            $table->string('type', 20)->index()->comment('annuality|ticket|other');
            $table->decimal('amount', 12, 2);
            $table->string('periodicity', 20)->comment('unique|daily|monthly|annual|per_event');
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key', 100)->unique();
            $table->text('value')->nullable();
            $table->string('type', 20)->default('string');
            $table->string('group', 50)->default('general')->index();
            $table->string('description')->nullable();
            $table->timestamps();
        });

        Schema::create('folio_sequences', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50)->unique();
            $table->string('prefix', 10);
            $table->unsignedTinyInteger('padding');
            $table->unsignedBigInteger('last_number')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('folio_sequences');
        Schema::dropIfExists('settings');
        Schema::dropIfExists('concepts');
        Schema::dropIfExists('routes');
        Schema::dropIfExists('tianguis');
        Schema::dropIfExists('business_types');
    }
};
