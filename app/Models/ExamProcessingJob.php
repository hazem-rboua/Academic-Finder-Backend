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
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'result' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
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
