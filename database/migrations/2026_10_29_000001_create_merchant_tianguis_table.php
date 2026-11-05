<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Un comerciante puede operar en varios tianguis (distintos días).
 * merchants.tianguis_id se conserva como tianguis principal.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('merchant_tianguis', function (Blueprint $table) {
            $table->foreignId('merchant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tianguis_id')->constrained('tianguis')->cascadeOnDelete();
            $table->timestamps();

            $table->primary(['merchant_id', 'tianguis_id']);
            $table->index('tianguis_id');
        });

        // Los comerciantes existentes conservan su tianguis principal.
        DB::table('merchant_tianguis')->insertUsing(
            ['merchant_id', 'tianguis_id', 'created_at', 'updated_at'],
            DB::table('merchants')->whereNotNull('tianguis_id')->select('id', 'tianguis_id', 'created_at', 'updated_at'),
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('merchant_tianguis');
    }
};
