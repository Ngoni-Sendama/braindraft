<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('study_diagrams', function (Blueprint $table) {
            $table->id();
            $table->string('subject_code');
            $table->string('title');
            $table->string('status')->default('processing');
            $table->string('path')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('study_diagrams');
    }
};
