<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Fragmentos del texto del CV con su embedding: son la evidencia textual que
 * el matching muestra por requisito.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cv_fragmentos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cv_id')->constrained('cvs')->cascadeOnDelete();
            $table->string('seccion', 40)->nullable();
            $table->unsignedSmallInteger('orden')->default(0);
            $table->text('texto');
            $table->vector('embedding', 384)->nullable();
            $table->timestamps();

            $table->index(['cv_id', 'orden']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cv_fragmentos');
    }
};
