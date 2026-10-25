<?php

namespace Tests\Feature;

use Tests\TestCase;

class TrustedCallerTest extends TestCase
{
    public function test_a_call_without_the_service_key_is_refused(): void
    {
        $this->flushHeaders();

        $this->postJson('/api/generate-pdf', [])->assertUnauthorized();
        $this->getJson('/api/exam-results/report-status?exam_code=X')->assertUnauthorized();
    }

    public function test_a_wrong_service_key_is_refused(): void
    {
        $this->flushHeaders()->withHeader('X-Service-Key', 'wrong');

        $this->postJson('/api/generate-pdf', [])->assertUnauthorized();
    }

    public function test_the_twindix_key_gets_past_the_lock(): void
    {
        // 422 = validation ran, so the lock let the call in.
        $this->postJson('/api/generate-pdf', [])->assertStatus(422);
    }
}
