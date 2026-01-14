<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class AiRecommendationService
{
    /**
     * Get job recommendations from AI API
     *
     * @param array $examResults
     * @param string $locale
     * @return array|null
     */
    public function getRecommendations(array $examResults, string $locale = 'en'): ?array
    {
        // Check if AI API is enabled
        if (!config('services.ai_api.enabled', true)) {
            Log::info('AI API is disabled via configuration');
            return null;
        }

        $baseUrl = config('services.ai_api.base_url');
        $timeout = config('services.ai_api.timeout', 10);
        $retryTimes = config('services.ai_api.retry_times', 3);

        // Determine endpoint based on locale
        $endpoint = $locale === 'ar' ? '/job-bar/recommend/ar' : '/job-bar/recommend';
        $url = $baseUrl . $endpoint;

        Log::info('Calling AI API for job recommendations', [
            'url' => $url,
            'locale' => $locale,
            'exam_data_keys' => array_keys($examResults),
        ]);

        $startTime = microtime(true);

        try {
            $response = Http::timeout($timeout)
                ->retry($retryTimes, function ($attempt) {
                    // Exponential backoff: 1s, 2s, 4s
                    return 1000 * pow(2, $attempt - 1);
                }, function ($exception, $request) {
                    // Retry on connection errors and timeouts
                    Log::warning('AI API request failed, retrying...', [
                        'error' => $exception->getMessage(),
                    ]);
                    return true;
                })
                ->post($url, $examResults);

            $duration = round((microtime(true) - $startTime) * 1000, 2);

            if ($response->successful()) {
                Log::info('AI API request successful', [
                    'duration_ms' => $duration,
                    'status_code' => $response->status(),
                ]);

                return $response->json();
            } else {
                Log::error('AI API returned error response', [
                    'status_code' => $response->status(),
                    'duration_ms' => $duration,
                    'response_body' => $response->body(),
                ]);

                return null;
            }
        } catch (Exception $e) {
            $duration = round((microtime(true) - $startTime) * 1000, 2);

            Log::error('AI API request failed after retries', [
                'error' => $e->getMessage(),
                'duration_ms' => $duration,
                'url' => $url,
            ]);

            return null;
        }
    }

    /**
     * Check if AI API is available
     *
     * @return bool
     */
    public function isAvailable(): bool
    {
        if (!config('services.ai_api.enabled', true)) {
            return false;
        }

        try {
            $baseUrl = config('services.ai_api.base_url');
            $response = Http::timeout(2)->get($baseUrl);
            return $response->successful();
        } catch (Exception $e) {
            return false;
        }
    }
}

