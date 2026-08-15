<?php

use App\Exceptions\ApiException;
use App\Helpers\ApiResponse;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(web: __DIR__.'/../routes/web.php', api: __DIR__.'/../routes/api.php', commands: __DIR__.'/../routes/console.php', health: '/up', then: function () {

        /**
         * ===============================================================
         * API Rate Limiter
         * ===============================================================
         *
         * Limits each client to 60 requests per minute.
         *
         * Authenticated users are identified by user ID.
         * Guests are identified by IP address.
         *
         * ===============================================================
         */
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(config('app.rate_limit.per_minute'))
                ->by($request->user()?->id ?: $request->ip());
        });

    }, )
    ->withMiddleware(function (Middleware $middleware): void {

        $middleware->api([
            SecurityHeaders::class,
        ]);

    })
    ->withExceptions(function (Exceptions $exceptions): void {

        /*
        |--------------------------------------------------------------------------
        | API Exception Handling
        |--------------------------------------------------------------------------
        |
        | Converts Laravel exceptions into the RemitIQ
        | standardized API response format.
        |
        */

        /*
        |--------------------------------------------------------------------------
        | Custom Application Exceptions
        |--------------------------------------------------------------------------
        */

        $exceptions->render(function (ApiException $exception, $request) {
            if (! $request->expectsJson()) {
                return null;
            }

            return ApiResponse::error(message: $exception->getMessage(), status: $exception->statusCode(), errors: $exception->errors());
        });

        /*
|--------------------------------------------------------------------------
| Validation Exceptions
|--------------------------------------------------------------------------
*/

        $exceptions->render(function (ValidationException $exception, $request) {

            if (! $request->expectsJson()) {
                return null;
            }

            return ApiResponse::validation(errors: $exception->errors(), message: $exception->getMessage());

        });

        /*
|--------------------------------------------------------------------------
| Authentication Exceptions
|--------------------------------------------------------------------------
*/

        $exceptions->render(function (AuthenticationException $exception, $request) {

            if (! $request->expectsJson()) {
                return null;
            }

            return ApiResponse::unauthorized($exception->getMessage() ?: 'Unauthorized.');

        });

        /*
|--------------------------------------------------------------------------
| Authorization Exceptions
|--------------------------------------------------------------------------
*/

        $exceptions->render(function (AuthorizationException $exception, $request) {

            if (! $request->expectsJson()) {
                return null;
            }

            return ApiResponse::forbidden($exception->getMessage() ?: 'Forbidden.');

        });

        /*
|--------------------------------------------------------------------------
| Model Not Found Exceptions
|--------------------------------------------------------------------------
*/

        $exceptions->render(function (ModelNotFoundException $exception, $request) {

            if (! $request->expectsJson()) {
                return null;
            }

            return ApiResponse::notFound(config('app.debug')
                    ? class_basename($exception->getModel()).' not found.'
                    : 'Resource not found.');

        });

        /*
|--------------------------------------------------------------------------
| HTTP Exceptions
|--------------------------------------------------------------------------
*/

        $exceptions->render(function (HttpExceptionInterface $exception, $request) {

            if (! $request->expectsJson()) {
                return null;
            }

            $message = $exception->getMessage();

            if (empty($message)) {

                $message = match ($exception->getStatusCode()) {

                    400 => 'Bad request.',
                    401 => 'Unauthorized.',
                    403 => 'Forbidden.',
                    404 => 'Resource not found.',
                    405 => 'Method not allowed.',
                    408 => 'Request timeout.',
                    409 => 'Conflict.',
                    415 => 'Unsupported media type.',
                    422 => 'Validation failed.',
                    429 => 'Too many requests.',

                    default => 'Request failed.',

                };

            }

            return ApiResponse::error(message: $message, status: $exception->getStatusCode());

        });

        /*
        |--------------------------------------------------------------------------
        | Fallback Exception Handler
        |--------------------------------------------------------------------------
        */

        $exceptions->render(function (Throwable $exception, $request) {

            if ($request->expectsJson()) {

                return ApiResponse::error(message: config('app.debug')
                        ? $exception->getMessage()
                        : 'Internal server error.', status: 500, errors: config('app.debug')
                        ? [
                            'exception' => class_basename($exception),
                            'file' => $exception->getFile(),
                            'line' => $exception->getLine(),
                        ]
                        : null);

            }

        });

    })->create();
