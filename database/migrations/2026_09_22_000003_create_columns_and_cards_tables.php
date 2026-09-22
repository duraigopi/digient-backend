<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('columns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('board_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->unsignedInteger('position');
            $table->timestamps();

            $table->index(['board_id', 'position']);
        });

        Schema::create('cards', function (Blueprint $table) {
            $table->id();
            // board_id is denormalised so access checks and "load whole board"
            // never need to join through columns. The move service keeps it in sync.
            $table->foreignId('board_id')->constrained()->cascadeOnDelete();
            $table->foreignId('column_id')->constrained()->cascadeOnDelete();
            $table->string('title', 200);
            $table->text('description')->nullable();
            $table->unsignedInteger('position');
            $table->timestamps();

            $table->index(['column_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cards');
        Schema::dropIfExists('columns');
    }
};
