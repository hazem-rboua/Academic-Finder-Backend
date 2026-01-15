<?php

namespace App\Services;

use App\Exceptions\ExamProcessingException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\App;

class ExamResultService
{
    /**
     * AI Recommendation Service
     *
     * @var AiRecommendationService
     */
    protected $aiRecommendationService;

    /**
     * Constructor
     *
     * @param AiRecommendationService $aiRecommendationService
     */
    public function __construct(AiRecommendationService $aiRecommendationService)
    {
        $this->aiRecommendationService = $aiRecommendationService;
    }
    /**
     * Process exam results and calculate job compatibility (synchronous)
     *
     * @param string $examCode
     * @return array
     * @throws ExamProcessingException
     */
    public function processExamResults(string $examCode): array
    {
        // 1. Validate and get exam
        $examEnrollment = $this->validateAndGetExam($examCode);

        // 2. Parse exam answers
        $answers = $this->parseExamAnswers($examEnrollment);

        // 3. Load CSV mapping
        $csvData = $this->loadCsvMapping();

        // 4. Process exam data
        $examResults = $this->processExamData($answers, $examEnrollment, $csvData);

        // 5. Get AI recommendations
        $locale = App::getLocale();
        Log::info('Getting AI recommendations', [
            'exam_code' => $examCode,
            'locale' => $locale,
        ]);

        $aiRecommendations = $this->aiRecommendationService->getRecommendations($examResults, $locale);

        if ($aiRecommendations !== null) {
            Log::info('AI recommendations received successfully', [
                'exam_code' => $examCode,
            ]);
            
            return $aiRecommendations;
        } else {
            Log::warning('AI recommendations not available, returning exam results', [
                'exam_code' => $examCode,
            ]);
            
            return $examResults;
        }
    }

    /**
     * Validate and get exam enrollment
     *
     * @param string $examCode
     * @return object
     * @throws ExamProcessingException
     */
    public function validateAndGetExam(string $examCode): object
    {
        $examEnrollment = $this->getExamEnrollment($examCode);
        
        if (!$examEnrollment) {
            throw ExamProcessingException::notFound(__('messages.exam_not_found'));
        }

        return $examEnrollment;
    }

    /**
     * Parse exam answers from enrollment
     *
     * @param object $examEnrollment
     * @return array
     * @throws ExamProcessingException
     */
    public function parseExamAnswers(object $examEnrollment): array
    {
        $answers = json_decode($examEnrollment->answers, true);
        
        if (!$answers || !is_array($answers)) {
            throw ExamProcessingException::invalidData(__('messages.invalid_exam_data'));
        }

        return $answers;
    }

    /**
     * Process exam data and calculate results
     *
     * @param array $answers
     * @param object $examEnrollment
     * @param array $csvData
     * @return array
     */
    public function processExamData(array $answers, object $examEnrollment, array $csvData): array
    {
        $csvMapping = $csvData['mapping'];
        $titleOrder = $csvData['title_order'];

        // Separate questions into branches (1xxxx-4xxxx) and environment (5xxxx)
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

        // Calculate selected branches
        $selectedBranches = $this->calculateSelectedBranches($branchAnswers, $csvMapping, $titleOrder);

        // Calculate environment status
        $environmentStatus = $this->calculateEnvironmentStatus($answers, $csvMapping);

        // Prepare exam results
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
     * @return array Returns ['mapping' => [...], 'title_order' => [...]]
     * @throws ExamProcessingException
     */
    public function loadCsvMapping(): array
    {
        $csvPath = public_path('AcademicFinderAlgorithm.csv');
        
        if (!file_exists($csvPath)) {
            throw ExamProcessingException::serverError(__('messages.csv_file_not_found'));
        }

        $mapping = [];
        $titleOrder = []; // Track the order of titles for each reference
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

            // Track title order per reference
            if (!isset($titleOrder[$reference])) {
                $titleOrder[$reference] = [];
            }
            if (!in_array($title, $titleOrder[$reference])) {
                $titleOrder[$reference][] = $title;
            }
        }

        fclose($handle);

        return [
            'mapping' => $mapping,
            'title_order' => $titleOrder,
        ];
    }

    /**
     * Calculate selected branches with competencies
     *
     * @param array $answers
     * @param array $csvMapping
     * @param array $titleOrder
     * @return array
     */
    private function calculateSelectedBranches(array $answers, array $csvMapping, array $titleOrder): array
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

            // Calculate competency values (5 competencies per job type) in the correct order
            $competencies = [];
            
            // Get the title order for this reference from CSV
            $orderedTitles = $titleOrder[$reference] ?? [];
            
            foreach ($orderedTitles as $title) {
                if (isset($titleGroups[$title])) {
                    $values = $titleGroups[$title];
                    // Count how many answers are 1
                    $onesCount = count(array_filter($values, fn($val) => $val === 1));
                    
                    // If 2 or more answers are 1, competency value is 1, otherwise 0
                    $competencyValue = $onesCount >= 2 ? 1 : 0;
                    $competencies[] = $competencyValue;
                    
                    // Debug logging
                    Log::debug("Job Type: {$jobTypeName}, Title: {$title}, Ones Count: {$onesCount}, Competency: {$competencyValue}, Values: " . json_encode($values));
                } else {
                    // Title not found in answers, default to 0
                    $competencies[] = 0;
                }
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
     * Calculate environment status from questions R33-R42
     * Each environment reference has 3 questions, apply 2+ ones = 1 rule
     *
     * @param array $answers All exam answers
     * @param array $csvMapping CSV mapping data
     * @return array
     */
    private function calculateEnvironmentStatus(array $answers, array $csvMapping): array
    {
        // Define the 10 environment references in order
        $environmentRefs = [
            'R33',  // Question 1: مرنة
            'R34',  // Question 2: ديناميكية
            'R35',  // Question 3: ابتكارية
            'R36',  // Question 4: تنافسية
            'R37',  // Question 5: حرة
            'R38',  // Question 6: موجه بالأهداف
            'R39',  // Question 7: الأعمال اليدوية
            'R40',  // Question 8: ضاغطة
            'R41',  // Question 9: استقلالية
            'R42',  // Question 10: العمل الجماعي
        ];

        $status = [];
        
        foreach ($environmentRefs as $index => $reference) {
            // Find all questions for this reference
            $refAnswers = [];
            
            foreach ($answers as $questionNumber => $answerValue) {
                if (!isset($csvMapping[$questionNumber])) {
                    continue;
                }
                
                $mapping = $csvMapping[$questionNumber];
                if ($mapping['reference'] === $reference) {
                    $refAnswers[] = (int) $answerValue;
                }
            }
            
            // Apply the 2+ ones = 1 rule
            $onesCount = count(array_filter($refAnswers, fn($val) => $val === 1));
            $selectedOption = $onesCount >= 2 ? 1 : 0;
            
            $status[] = [
                'question' => $index + 1,
                'selected_option' => $selectedOption,
            ];
            
            Log::debug("Environment Q" . ($index + 1) . " ({$reference}): Ones Count: {$onesCount}, Selected: {$selectedOption}, Values: " . json_encode($refAnswers));
        }

        return $status;
    }

}

