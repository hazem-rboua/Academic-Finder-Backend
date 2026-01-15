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
        Schema::create('exam_processing_jobs', function (Blueprint $table) {
            $table->id();
            $table->string('job_id')->unique()->index();
            $table->string('exam_code')->index();
            $table->enum('status', ['pending', 'processing', 'completed', 'failed'])->default('pending');
            $table->integer('progress')->default(0);
            $table->string('current_step')->nullable();
            $table->json('result')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('exam_processing_jobs');
    }
};
