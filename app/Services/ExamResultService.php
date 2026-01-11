<?php

namespace App\Services;

use App\Exceptions\ExamProcessingException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ExamResultService
{
    /**
     * Process exam results and calculate job compatibility
     *
     * @param string $examCode
     * @return array
     * @throws ExamProcessingException
     */
    public function processExamResults(string $examCode): array
    {
        // 1. Get exam enrollment data from external database
        $examEnrollment = $this->getExamEnrollment($examCode);
        
        if (!$examEnrollment) {
            throw ExamProcessingException::notFound(__('messages.exam_not_found'));
        }

        // 2. Parse answers JSON
        $answers = json_decode($examEnrollment->answers, true);
        
        if (!$answers || !is_array($answers)) {
            throw ExamProcessingException::invalidData(__('messages.invalid_exam_data'));
        }

        // 3. Load CSV mapping
        $csvMapping = $this->loadCsvMapping();

        // 4. Separate questions into branches (1xxxx-4xxxx) and environment (5xxxx)
        $branchAnswers = [];
        $environmentAnswers = [];
        
        Log::info("Total questions in exam: " . count($answers));
        
        foreach ($answers as $questionNumber => $answerValue) {
            $questionNumber = (string) $questionNumber;
            $firstDigit = substr($questionNumber, 0, 1);
            
            if (in_array($firstDigit, ['1', '2', '3', '4'])) {
                // Branch questions (job types)
                $branchAnswers[$questionNumber] = $answerValue;
            } elseif ($firstDigit === '5') {
                // Environment questions
                $environmentAnswers[$questionNumber] = $answerValue;
            }
        }
        
        Log::info("Branch questions: " . count($branchAnswers));
        Log::info("Environment questions: " . count($environmentAnswers));
        Log::info("Questions not in CSV: " . count(array_diff_key($branchAnswers, $csvMapping)));

        // 5. Calculate selected branches
        $selectedBranches = $this->calculateSelectedBranches($branchAnswers, $csvMapping);

        // 6. Calculate environment status
        $environmentStatus = $this->calculateEnvironmentStatus($environmentAnswers);

        return [
            'job_title' => $examEnrollment->job_title ?? null,
            'industry' => $examEnrollment->industry ?? null,
            'seniority' => $examEnrollment->seniority ?? null,
            'selected_branches' => $selectedBranches,
            'environment_status' => $environmentStatus,
        ];
    }

    /**
     * Get exam enrollment from external database
     *
     * @param string $examCode
     * @return object|null
     */
    private function getExamEnrollment(string $examCode): ?object
    {
        return DB::connection('external_api')
            ->table('exam_enrollments')
            ->where('code', $examCode)
            ->first();
    }

    /**
     * Load CSV mapping from public directory
     *
     * @return array
     * @throws ExamProcessingException
     */
    private function loadCsvMapping(): array
    {
        $csvPath = public_path('AcademicFinderAlgorithm.csv');
        
        if (!file_exists($csvPath)) {
            throw ExamProcessingException::serverError(__('messages.csv_file_not_found'));
        }

        $mapping = [];
        $handle = fopen($csvPath, 'r');
        
        if ($handle === false) {
            throw ExamProcessingException::serverError(__('messages.csv_file_read_error'));
        }

        // Skip header row
        fgetcsv($handle);

        while (($row = fgetcsv($handle)) !== false) {
            // CSV structure: code, Title, Reference, ...
            $questionNumber = trim($row[0] ?? '');
            $title = trim($row[1] ?? '');
            $reference = trim($row[2] ?? '');

            // Skip empty rows or title rows
            if (empty($questionNumber) || empty($reference)) {
                continue;
            }

            // Skip title rows (e.g., "R17-Title")
            if (strpos($reference, '-Title') !== false) {
                continue;
            }

            $mapping[$questionNumber] = [
                'title' => $title,
                'reference' => $reference,
            ];
        }

        fclose($handle);

        return $mapping;
    }

    /**
     * Calculate selected branches with competencies
     *
     * @param array $answers
     * @param array $csvMapping
     * @return array
     */
    private function calculateSelectedBranches(array $answers, array $csvMapping): array
    {
        // Define the 16 job types in order with their reference codes
        $jobTypes = [
            'R17' => 'Open Thinking Jobs',
            'R18' => 'Structured Thinking Jobs',
            'R19' => 'Reference Thinking Jobs',
            'R20' => 'Critical Thinking Jobs',
            'R21' => 'Sales Communication Jobs',
            'R22' => 'Public Communication Jobs',
            'R23' => 'Educational Communication Jobs',
            'R24' => 'Command Communication Jobs',
            'R25' => 'Hard Labor Jobs',
            'R26' => 'Paperwork Jobs',
            'R27' => 'Craftsmanship Jobs',
            'R28' => 'Tech-Work Jobs',
            'R29' => 'Social Service Jobs',
            'R30' => 'Rescue and Care Jobs',
            'R31' => 'Fancy Serving Jobs',
            'R32' => 'Basic Serving Jobs',
        ];

        $results = [];

        foreach ($jobTypes as $reference => $jobTypeName) {
            // Group answers by title for this reference
            $titleGroups = [];
            
            foreach ($answers as $questionNumber => $answerValue) {
                if (!isset($csvMapping[$questionNumber])) {
                    Log::debug("Question {$questionNumber} not found in CSV mapping");
                    continue;
                }

                $mapping = $csvMapping[$questionNumber];
                if ($mapping['reference'] !== $reference) {
                    continue;
                }

                $title = $mapping['title'];
                if (!isset($titleGroups[$title])) {
                    $titleGroups[$title] = [];
                }
                $titleGroups[$title][] = (int) $answerValue;
            }

            // Calculate competency values (5 competencies per job type)
            $competencies = [];
            foreach ($titleGroups as $title => $values) {
                // Count how many answers are 1
                $onesCount = count(array_filter($values, fn($val) => $val === 1));
                
                // If 2 or more answers are 1, competency value is 1, otherwise 0
                $competencyValue = $onesCount >= 2 ? 1 : 0;
                $competencies[] = $competencyValue;
                
                // Debug logging
                Log::debug("Job Type: {$jobTypeName}, Title: {$title}, Ones Count: {$onesCount}, Competency: {$competencyValue}, Values: " . json_encode($values));
            }

            // Ensure we always have exactly 5 competencies (pad with 0 if needed)
            while (count($competencies) < 5) {
                $competencies[] = 0;
            }
            $competencies = array_slice($competencies, 0, 5);

            $results[] = [
                'job_type' => $jobTypeName,
                'chosen_competencies' => $competencies,
            ];
        }

        return $results;
    }

    /**
     * Calculate environment status from questions
     *
     * @param array $environmentAnswers
     * @return array
     */
    private function calculateEnvironmentStatus(array $environmentAnswers): array
    {
        $status = [];
        
        // Sort by question number to maintain order
        ksort($environmentAnswers);
        
        $questionIndex = 1;
        foreach ($environmentAnswers as $questionNumber => $answerValue) {
            $status[] = [
                'question' => $questionIndex,
                'selected_option' => (int) $answerValue,
            ];
            $questionIndex++;
        }

        // Ensure we have exactly 10 questions (pad with 0 if needed)
        while (count($status) < 10) {
            $status[] = [
                'question' => count($status) + 1,
                'selected_option' => 0,
            ];
        }

        return array_slice($status, 0, 10);
    }

}

