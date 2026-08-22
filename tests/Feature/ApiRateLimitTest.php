<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class ApiRateLimitTest extends TestCase
{
    use RefreshDatabase;

    private function requestIp(): string
    {
        return '10.200.'.random_int(1, 254).'.'.random_int(1, 254);
    }

    private function loginRequest(string $ip)
    {
        return $this
            ->withServerVariables([
                'REMOTE_ADDR' => $ip,
            ])
            ->postJson('/api/v1/auth/login', [
                'email' => 'ratelimit@example.com',
                'password' => 'Password123!',
            ]);
    }

    private function clearRateLimit(string $ip): void
    {
        RateLimiter::clear($ip);
        RateLimiter::clear("api|{$ip}");
    }

    public function test_api_requests_are_rate_limited(): void
    {
        $limit = (int) config('app.rate_limit.per_minute');
        $ip = $this->requestIp();

        $this->assertGreaterThan(0, $limit);

        $this->clearRateLimit($ip);

        for ($i = 0; $i < $limit; $i++) {
            $response = $this->loginRequest($ip);

            $this->assertNotSame(429, $response->status(), "Request {$i} was rate limited before reaching the configured limit.");
        }

        $response = $this->loginRequest($ip);

        $response->assertStatus(429);
    }

    public function test_rate_limiter_allows_requests_below_limit(): void
    {
        $limit = (int) config('app.rate_limit.per_minute');
        $ip = $this->requestIp();

        $this->assertGreaterThan(1, $limit);

        $this->clearRateLimit($ip);

        $response = $this->loginRequest($ip);

        // Authentication may fail, but rate limiting must not.
        $this->assertNotSame(429, $response->status());
    }

    public function test_rate_limit_configuration_is_positive(): void
    {
        $limit = (int) config('app.rate_limit.per_minute');

        $this->assertGreaterThan(0, $limit);
    }
}
