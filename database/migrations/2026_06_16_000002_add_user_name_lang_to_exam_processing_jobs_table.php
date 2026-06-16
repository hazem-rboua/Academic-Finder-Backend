<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exam_processing_jobs', function (Blueprint $table) {
            if (!Schema::hasColumn('exam_processing_jobs', 'user_name')) {
                $table->string('user_name')->nullable()->after('pdf_path');
            }
            if (!Schema::hasColumn('exam_processing_jobs', 'lang')) {
                $table->string('lang', 5)->default('en')->after('user_name');
            }
        });
    }

    public function down(): void
    {
        Schema::table('exam_processing_jobs', function (Blueprint $table) {
            foreach (['lang', 'user_name'] as $col) {
                if (Schema::hasColumn('exam_processing_jobs', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
