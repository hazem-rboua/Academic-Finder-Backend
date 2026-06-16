<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table("exam_processing_jobs", function (Blueprint $table) {
            if (!Schema::hasColumn("exam_processing_jobs", "pdf_ready")) {
                $table->boolean("pdf_ready")->default(false)->after("result");
            }
            if (!Schema::hasColumn("exam_processing_jobs", "pdf_path")) {
                $table->string("pdf_path")->nullable()->after("pdf_ready");
            }
        });
    }

    public function down(): void
    {
        Schema::table("exam_processing_jobs", function (Blueprint $table) {
            if (Schema::hasColumn("exam_processing_jobs", "pdf_path")) {
                $table->dropColumn("pdf_path");
            }
            if (Schema::hasColumn("exam_processing_jobs", "pdf_ready")) {
                $table->dropColumn("pdf_ready");
            }
        });
    }
};
