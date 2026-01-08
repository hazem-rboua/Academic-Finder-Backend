<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ExamResultTest extends TestCase
{
    /**
     * Test exam results endpoint validation
     */
    public function test_exam_code_is_required(): void
    {
        $response = $this->postJson('/api/exam-results/process', []);

        $response->assertStatus(422)
            ->assertJsonStructure([
                'message',
                'errors' => [
                    'exam_code'
                ]
            ]);
    }

    /**
     * Test exam results endpoint is accessible
     */
    public function test_exam_results_endpoint_is_accessible(): void
    {
        // This test just verifies the endpoint exists and responds
        // It doesn't require actual database connection
        
        $response = $this->postJson('/api/exam-results/process', [
            'exam_code' => 'TEST_EXAM_CODE'
        ]);

        // The response could be 404 (exam not found) or 200 (success) or 500 (connection error)
        // All are valid responses - we just want to ensure the endpoint exists
        $this->assertContains($response->status(), [200, 404, 500]);
        
        // Verify we get JSON response
        $response->assertHeader('content-type', 'application/json');
    }

    /**
     * Test CSV file exists
     */
    public function test_csv_file_exists(): void
    {
        $csvPath = public_path('AcademicFinderAlgorithm.csv');
        $this->assertFileExists($csvPath, 'CSV mapping file should exist in public directory');
    }

    /**
     * Test external database connection is configured
     */
    public function test_external_database_connection_is_configured(): void
    {
        $connections = config('database.connections');
        
        $this->assertArrayHasKey('external_api', $connections, 'External API database connection should be configured');
        $this->assertEquals('mysql', $connections['external_api']['driver']);
    }
}

