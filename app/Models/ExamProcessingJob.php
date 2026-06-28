<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExamProcessingJob extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'exam_processing_jobs';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'job_id',
        'exam_code',
        'status',
        'progress',
        'current_step',
        'result',
        'error_message',
        'started_at',
        'completed_at',
        'pdf_ready',
        'pdf_path',
        'user_name',
        'lang',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'result' => 'array',
        'progress' => 'integer',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'pdf_ready' => 'boolean',
    ];

    /**
     * Mark job as processing
     *
     * @return void
     */
    public function markAsProcessing(): void
    {
        $this->update([
            'status' => 'processing',
            'started_at' => now(),
        ]);
    }

    /**
     * Update job progress
     *
     * @param int $progress
     * @param string $step
     * @return void
     */
    public function updateProgress(int $progress, string $step): void
    {
        $this->update([
            'progress' => $progress,
            'current_step' => $step,
        ]);
    }

    /**
     * Mark job as completed
     *
     * @param array $result
     * @return void
     */
    public function markAsCompleted(array $result): void
    {
        $this->update([
            'status' => 'completed',
            'progress' => 100,
            'result' => $result,
            'completed_at' => now(),
        ]);
    }

    /**
     * Mark job as failed
     *
     * @param string $error
     * @return void
     */
    public function markAsFailed(string $error): void
    {
        $this->update([
            'status' => 'failed',
            'error_message' => $error,
            'completed_at' => now(),
        ]);
    }

    /**
     * Build the human-readable report name used for BOTH the report title
     * and the downloaded file name, so they always match.
     * Format: "Academic Finder - First Second - code - date".
     *
     * @return string
     */
    public function reportName(): string
    {
        $parts = preg_split('/\s+/', trim((string) $this->user_name), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $name  = trim(implode(' ', array_slice($parts, 0, 2)));
        if ($name === '') {
            $name = 'Candidate';
        }

        $when = $this->completed_at ?? $this->created_at;
        $date = $when ? $when->format('Y-m-d') : now()->format('Y-m-d');

        return 'Academic Finder - ' . $name . ' - ' . $this->exam_code . ' - ' . $date;
    }

    /**
     * File-system-safe version of reportName() with the .pdf extension.
     *
     * @return string
     */
    public function reportFileName(): string
    {
        $safe = preg_replace('/[\/\\\\:*?"<>|]+/', '', $this->reportName());
        $safe = trim(preg_replace('/\s+/', ' ', (string) $safe));

        return $safe . '.pdf';
    }

    /**
     * Scope a query to only include completed jobs.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeWhereCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    /**
     * Scope a query to only include failed jobs.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeWhereFailed($query)
    {
        return $query->where('status', 'failed');
    }

    /**
     * Scope a query to only include processing jobs.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeWhereProcessing($query)
    {
        return $query->where('status', 'processing');
    }
}
