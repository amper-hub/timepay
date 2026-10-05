<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use App\Support\SafeValidationMessages;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withCommands()
    ->withBroadcasting(
        __DIR__.'/../routes/channels.php',
        ['middleware' => ['auth:sanctum']]
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request, \Throwable $exception): bool =>
                $request->is('api/*') || $request->expectsJson()
        );

        $exceptions->render(function (Throwable $exception, Request $request) {
            $wantsJson = $request->is('api/*') || $request->expectsJson();

            if (! $wantsJson && $exception instanceof AuthenticationException) {
                return null;
            }

            if (! $wantsJson && $exception instanceof ValidationException) {
                return redirect($exception->redirectTo ?: url()->previous())
                    ->withInput($request->except([
                        'password',
                        'password_confirmation',
                        'current_password',
                        'new_password',
                        'new_password_confirmation',
                    ]))
                    ->withErrors(SafeValidationMessages::sanitize($exception->errors()), $exception->errorBag);
            }

            $status = match (true) {
                $exception instanceof ValidationException => 422,
                $exception instanceof AuthenticationException => 401,
                $exception instanceof AuthorizationException => 403,
                $exception instanceof ModelNotFoundException => 404,
                $exception instanceof HttpExceptionInterface => $exception->getStatusCode(),
                default => 500,
            };

            $message = match (true) {
                $status === 400, $status === 422 => 'Please check the information you entered and try again.',
                $status === 401 && str_ends_with($request->path(), 'api/auth/login') => 'The email or password you entered is incorrect.',
                $status === 401 => 'Your session has expired. Please sign in again.',
                $status === 403 => "You don't have permission to perform this action.",
                $status === 404 => "We couldn't find what you're looking for.",
                $status === 408 => 'The request took too long. Please try again.',
                $status === 429 => 'Too many attempts. Please wait a moment and try again.',
                $status === 503 => 'This service is temporarily unavailable. Please try again shortly.',
                $status >= 500 => 'Something went wrong on our end. Please try again later.',
                default => 'Something went wrong. Please try again.',
            };

            if ($wantsJson) {
                $payload = ['message' => $message];

                if ($exception instanceof ValidationException) {
                    $safeErrors = SafeValidationMessages::sanitize($exception->errors());
                    if ($safeErrors !== []) {
                        $payload['errors'] = $safeErrors;
                    }
                }

                return response()->json($payload, $status);
            }

            return response()->view('errors.generic', ['message' => $message], $status);
        });
    })->create();
