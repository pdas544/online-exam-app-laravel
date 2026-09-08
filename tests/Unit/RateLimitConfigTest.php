<?php

namespace Tests\Unit;

use Tests\TestCase;

class RateLimitConfigTest extends TestCase
{
    public function test_rate_limits_resolve_with_env_fallbacks(): void
    {
        $this->assertEquals((int) env('EXAM_RATE_LIMIT', 1000), config('rate_limits.exam'));
        $this->assertEquals((int) env('AUTH_RATE_LIMIT', 1000), config('rate_limits.auth'));
    }
}
