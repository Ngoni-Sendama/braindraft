<?php

use App\Models\Subject;
use App\Models\User;
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
        Schema::create('study_sets', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(User::class, 'user_id')
                ->constrained()
                ->cascadeOnDelete()
                ->nullOnDelete();
            $table->foreignIdFor(Subject::class, 'subject_id')
                ->constrained()
                ->cascadeOnDelete()
                ->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->enum('visibility', ['private', 'public'])->default('private');
            $table->enum('source_type', ['manual', 'pdf', 'image', 'audio', 'youtube', 'webpage']);
            $table->enum('status', ['draft', 'processing', 'ready', 'failed'])->default('draft');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('study_sets');
    }
};
