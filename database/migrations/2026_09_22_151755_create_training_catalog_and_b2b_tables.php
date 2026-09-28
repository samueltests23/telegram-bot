<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('training_areas', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->string('icon')->nullable();
            $table->timestamps();
        });

        Schema::create('courses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('training_area_id')->constrained('training_areas')->onDelete('cascade');
            $table->string('title');
            $table->string('duration');
            $table->text('purpose');
            $table->string('certification')->default('Certificado de Participación avalado por CONATEL y entes universitarios.');
            $table->timestamps();
        });

        Schema::create('b2b_quotes', function (Blueprint $table) {
            $table->id();
            $table->string('telegram_chat_id');
            $table->string('company_name')->nullable();
            $table->string('rif')->nullable();
            $table->string('employees_count')->nullable();
            $table->string('area_interest')->nullable();
            $table->string('status')->default('pending');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('b2b_quotes');
        Schema::dropIfExists('courses');
        Schema::dropIfExists('training_areas');
    }
};