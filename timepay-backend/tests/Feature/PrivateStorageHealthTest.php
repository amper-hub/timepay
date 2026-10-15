<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class PrivateStorageHealthTest extends TestCase
{
    use RefreshDatabase;

    public function test_storage_health_requires_authentication(): void
    {
        $this->getJson('/api/health/storage')->assertUnauthorized();
    }

    public function test_storage_health_only_returns_status_and_removes_its_probe(): void
    {
        Storage::fake('private');
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/health/storage')
            ->assertOk()
            ->assertExactJson(['status' => 'ok'])
            ->assertDontSee('health-checks')
            ->assertDontSee('storage/app/private');

        $this->assertSame([], Storage::disk('private')->allFiles());
    }

    public function test_storage_health_hides_private_disk_failures(): void
    {
        $disk = Mockery::mock();
        $disk->shouldReceive('put')
            ->once()
            ->with(Mockery::type('string'), 'timepay-storage-health-check')
            ->andThrow(new RuntimeException('private path and credentials must stay hidden'));
        $disk->shouldReceive('delete')
            ->once()
            ->with(Mockery::type('string'))
            ->andReturn(true);
        Storage::shouldReceive('disk')->once()->with('private')->andReturn($disk);

        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/health/storage')
            ->assertStatus(503)
            ->assertExactJson(['status' => 'unavailable'])
            ->assertDontSee('private path')
            ->assertDontSee('credentials');
    }
}
