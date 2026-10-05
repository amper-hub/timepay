<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Tests\TestCase;

class ApiErrorHandlingTest extends TestCase
{
    use RefreshDatabase;

    public function test_mobile_login_uses_a_friendly_message_for_invalid_credentials(): void
    {
        $user = User::factory()->create();

        $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'incorrect-password',
        ])
            ->assertUnprocessable()
            ->assertJsonPath('errors.email.0', 'The email or password you entered is incorrect.')
            ->assertDontSee('SQLSTATE');
    }

    public function test_mobile_login_succeeds_with_valid_credentials(): void
    {
        $user = User::factory()->create();

        $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ])
            ->assertOk()
            ->assertJsonStructure(['token', 'user' => ['id', 'email'], 'company' => ['id']]);
    }

    public function test_api_exceptions_return_safe_messages_for_common_statuses(): void
    {
        Route::get('/api/__safe-error-test/{status}', function (int $status) {
            if ($status === 500) {
                throw new RuntimeException('SQLSTATE[23000] C:\\app\\vendor\\database.php');
            }

            if ($status === 422) {
                throw ValidationException::withMessages([
                    'field' => ['SQLSTATE[23000] /srv/app/vendor/database.php'],
                ]);
            }

            abort($status, 'SQLSTATE[23000] /srv/app/vendor/database.php');
        });

        $expected = [
            400 => 'Please check the information you entered and try again.',
            401 => 'Your session has expired. Please sign in again.',
            403 => "You don't have permission to perform this action.",
            404 => "We couldn't find what you're looking for.",
            422 => 'Please check the information you entered and try again.',
            500 => 'Something went wrong on our end. Please try again later.',
            503 => 'This service is temporarily unavailable. Please try again shortly.',
        ];

        foreach ($expected as $status => $message) {
            $response = $this->getJson('/api/__safe-error-test/'.$status)
                ->assertStatus($status)
                ->assertJsonPath('message', $message)
                ->assertDontSee('SQLSTATE')
                ->assertDontSee('/srv/app')
                ->assertDontSee('RuntimeException');

            if ($status === 422) {
                $response->assertJsonPath('errors.field.0', 'Please check this field and try again.');
            }
        }
    }

    public function test_safe_business_validation_messages_are_preserved(): void
    {
        Route::get('/api/__safe-business-error-test', function () {
            throw ValidationException::withMessages([
                'leave_type' => ['Your leave request overlaps with an existing request.'],
            ]);
        });

        $this->getJson('/api/__safe-business-error-test')
            ->assertUnprocessable()
            ->assertJsonPath('errors.leave_type.0', 'Your leave request overlaps with an existing request.');
    }

    public function test_technical_validation_details_are_replaced_with_safe_copy(): void
    {
        Route::get('/api/__unsafe-validation-test', function () {
            throw ValidationException::withMessages([
                'email' => ['Duplicate entry for key users.email at db.internal:3306'],
                'password' => ['DB_PASSWORD=hunter2'],
            ]);
        });

        $this->getJson('/api/__unsafe-validation-test')
            ->assertUnprocessable()
            ->assertJsonPath('errors.email.0', 'Please check this field and try again.')
            ->assertJsonPath('errors.password.0', 'Please check this field and try again.')
            ->assertDontSee('db.internal')
            ->assertDontSee('hunter2');
    }

    public function test_unexpected_web_exceptions_do_not_render_debug_details(): void
    {
        config(['app.debug' => true]);

        Route::get('/__safe-error-test/web', function () {
            throw new RuntimeException('SQLSTATE[23000] C:\\app\\vendor\\database.php');
        });

        $this->get('/__safe-error-test/web')
            ->assertStatus(500)
            ->assertSee('Something went wrong on our end. Please try again later.')
            ->assertDontSee('SQLSTATE')
            ->assertDontSee('/app/vendor')
            ->assertDontSee('RuntimeException');
    }
}
