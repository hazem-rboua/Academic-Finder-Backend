<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected const SERVICE_KEY = 'test-service-key';

    /** Tests call the API as the Twindix API does: with the service key (TrustedCaller). */
    protected function setUp(): void
    {
        parent::setUp();

        config(['services.twindix.key' => self::SERVICE_KEY]);
        $this->withHeader('X-Service-Key', self::SERVICE_KEY);
    }
}
