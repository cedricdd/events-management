<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete()->cascadeOnUpdate();
            $table->string('name');
            $table->text('description');
            $table->dateTime('start_date');
            $table->dateTime('end_date');
            $table->string('location');
            $table->integer('cost')->default(0);
            $table->boolean('public')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};
