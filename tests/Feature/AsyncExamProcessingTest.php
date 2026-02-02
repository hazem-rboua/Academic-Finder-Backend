<?php

namespace Tests\Feature;

use App\Models\ExamProcessingJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class AsyncExamProcessingTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that exam processing dispatches a job and returns 202 with job_id
     */
    public function test_process_endpoint_dispatches_job_and_returns_job_id(): void
    {
        Queue::fake();

        $response = $this->postJson('/api/exam-results/process', [
            'exam_code' => 'TEST_EXAM_CODE',
        ]);

        $response->assertStatus(202)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'job_id',
                    'status_url',
                    'estimated_time_seconds',
                ],
            ])
            ->assertJson([
                'success' => true,
            ]);

        // Verify job was created in database
        $this->assertDatabaseHas('exam_processing_jobs', [
            'exam_code' => 'TEST_EXAM_CODE',
            'status' => 'pending',
            'progress' => 0,
        ]);
    }

    /**
     * Test that status endpoint returns correct data for pending job
     */
    public function test_status_endpoint_returns_pending_job_data(): void
    {
        $job = ExamProcessingJob::create([
            'job_id' => 'test-job-id-123',
            'exam_code' => 'TEST_EXAM',
            'status' => 'pending',
            'progress' => 0,
        ]);

        $response = $this->getJson('/api/exam-results/status/test-job-id-123');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'status' => 'pending',
                    'progress' => 0,
                ],
            ]);
    }

    /**
     * Test that status endpoint returns correct data for processing job
     */
    public function test_status_endpoint_returns_processing_job_data(): void
    {
        $job = ExamProcessingJob::create([
            'job_id' => 'test-job-id-456',
            'exam_code' => 'TEST_EXAM',
            'status' => 'processing',
            'progress' => 50,
            'current_step' => 'Getting AI recommendations...',
            'started_at' => now(),
        ]);

        $response = $this->getJson('/api/exam-results/status/test-job-id-456');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'status' => 'processing',
                    'progress' => 50,
                    'current_step' => 'Getting AI recommendations...',
                ],
            ])
            ->assertJsonStructure([
                'data' => [
                    'started_at',
                ],
            ]);
    }

    /**
     * Test that status endpoint returns completed job with results
     */
    public function test_status_endpoint_returns_completed_job_with_results(): void
    {
        $results = [
            'job_title' => 'Test Job',
            'industry' => 'Test Industry',
            'selected_branches' => [],
            'environment_status' => [],
        ];

        $job = ExamProcessingJob::create([
            'job_id' => 'test-job-id-789',
            'exam_code' => 'TEST_EXAM',
            'status' => 'completed',
            'progress' => 100,
            'result' => $results,
            'started_at' => now()->subSeconds(40),
            'completed_at' => now(),
        ]);

        $response = $this->getJson('/api/exam-results/status/test-job-id-789');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'status' => 'completed',
                    'progress' => 100,
                    'result' => $results,
                ],
            ])
            ->assertJsonStructure([
                'data' => [
                    'completed_at',
                ],
            ]);
    }

    /**
     * Test that status endpoint returns failed job with error message
     */
    public function test_status_endpoint_returns_failed_job_with_error(): void
    {
        $job = ExamProcessingJob::create([
            'job_id' => 'test-job-id-error',
            'exam_code' => 'TEST_EXAM',
            'status' => 'failed',
            'progress' => 25,
            'error_message' => 'AI API error (HTTP 500): Internal server error',
            'started_at' => now()->subSeconds(5),
            'completed_at' => now(),
        ]);

        $response = $this->getJson('/api/exam-results/status/test-job-id-error');

        $response->assertStatus(200)
            ->assertJson([
                'success' => false,
                'data' => [
                    'status' => 'failed',
                    'progress' => 25,
                    'error_message' => 'AI API error (HTTP 500): Internal server error',
                ],
            ]);
    }

    /**
     * Test that status endpoint returns 404 for non-existent job
     */
    public function test_status_endpoint_returns_404_for_invalid_job_id(): void
    {
        $response = $this->getJson('/api/exam-results/status/non-existent-job-id');

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
            ]);
    }

    /**
     * Test that process endpoint validates exam_code is required
     */
    public function test_process_endpoint_validates_exam_code_required(): void
    {
        $response = $this->postJson('/api/exam-results/process', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['exam_code']);
    }
}
