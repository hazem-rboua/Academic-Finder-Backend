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

        // 4. Calculate reference values based on the algorithm
        $referenceValues = $this->calculateReferenceValues($answers, $csvMapping);

        // 5. Get job titles from template
        $jobTitles = $this->getJobTitles();

        // 6. Match references with job titles
        $results = $this->matchReferencesWithTitles($referenceValues, $jobTitles);

        return [
            'exam_code' => $examCode,
            'total_questions' => count($answers),
            'reference_values' => $referenceValues,
            'job_compatibility' => $results,
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
            ->where('exam_code', $examCode)
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
     * Calculate reference values based on the algorithm
     * 
     * Algorithm: Group answers by title. If 2 or more answers with the same title have value 1,
     * then the final value for that title is 1. Otherwise, it's 0.
     *
     * @param array $answers
     * @param array $csvMapping
     * @return array
     */
    private function calculateReferenceValues(array $answers, array $csvMapping): array
    {
        // Group answers by title
        $titleGroups = [];
        
        foreach ($answers as $questionNumber => $answerValue) {
            $questionNumber = (string) $questionNumber;
            
            if (!isset($csvMapping[$questionNumber])) {
                Log::warning("Question number {$questionNumber} not found in CSV mapping");
                continue;
            }

            $title = $csvMapping[$questionNumber]['title'];
            $reference = $csvMapping[$questionNumber]['reference'];

            if (!isset($titleGroups[$title])) {
                $titleGroups[$title] = [
                    'reference' => $reference,
                    'answers' => [],
                ];
            }

            $titleGroups[$title]['answers'][] = (int) $answerValue;
        }

        // Calculate final values
        $referenceValues = [];
        
        foreach ($titleGroups as $title => $data) {
            $reference = $data['reference'];
            $answers = $data['answers'];
            
            // Count how many answers have value 1
            $onesCount = count(array_filter($answers, fn($val) => $val === 1));
            
            // If 2 or more answers are 1, final value is 1, otherwise 0
            $finalValue = $onesCount >= 2 ? 1 : 0;

            if (!isset($referenceValues[$reference])) {
                $referenceValues[$reference] = [
                    'reference' => $reference,
                    'titles' => [],
                    'total_value' => 0,
                ];
            }

            $referenceValues[$reference]['titles'][$title] = [
                'title' => $title,
                'value' => $finalValue,
                'ones_count' => $onesCount,
                'total_questions' => count($answers),
            ];

            $referenceValues[$reference]['total_value'] += $finalValue;
        }

        return array_values($referenceValues);
    }

    /**
     * Get job titles from template table
     *
     * @return array
     */
    private function getJobTitles(): array
    {
        $template = DB::connection('external_api')
            ->table('template')
            ->where('id', 6)
            ->first();

        if (!$template || empty($template->references)) {
            return [];
        }

        $references = json_decode($template->references, true);
        
        if (!$references || !is_array($references)) {
            return [];
        }

        return $references;
    }

    /**
     * Match reference values with job titles from template
     *
     * @param array $referenceValues
     * @param array $jobTitles
     * @return array
     */
    private function matchReferencesWithTitles(array $referenceValues, array $jobTitles): array
    {
        $results = [];

        // Create a lookup map for job titles by reference
        $referenceTitleMap = [];
        foreach ($jobTitles as $jobTitle) {
            $reference = $jobTitle['reference'] ?? null;
            if ($reference) {
                if (!isset($referenceTitleMap[$reference])) {
                    $referenceTitleMap[$reference] = [];
                }
                $referenceTitleMap[$reference][] = $jobTitle;
            }
        }

        // Match calculated values with job titles
        foreach ($referenceValues as $refData) {
            $reference = $refData['reference'];
            
            $results[] = [
                'reference' => $reference,
                'total_value' => $refData['total_value'],
                'titles' => $refData['titles'],
                'job_descriptions' => $referenceTitleMap[$reference] ?? [],
            ];
        }

        // Sort by total_value descending to show best matches first
        usort($results, fn($a, $b) => $b['total_value'] <=> $a['total_value']);

        return $results;
    }
}

