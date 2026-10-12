<?php

namespace Tests\Feature;

use Tests\TestCase;

class HealthEndpointTest extends TestCase
{
    public function test_laravel_health_endpoint_returns_success_without_authentication(): void
    {
        $this->get('/up')->assertOk();
    }
}
