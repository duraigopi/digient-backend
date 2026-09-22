<?php

namespace App\Exceptions;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;
use Laravel\Lumen\Exceptions\Handler as ExceptionHandler;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * A list of the exception types that should not be reported.
     *
     * @var array
     */
    protected $dontReport = [
        AuthorizationException::class,
        HttpException::class,
        ModelNotFoundException::class,
        ValidationException::class,
    ];

    /**
     * Report or log an exception.
     *
     * This is a great spot to send exceptions to Sentry, Bugsnag, etc.
     *
     * @param  \Throwable  $exception
     * @return void
     *
     * @throws \Exception
     */
    public function report(Throwable $exception)
    {
        parent::report($exception);
    }

    /**
     * Render every exception as JSON so the frontend always receives a
     * consistent {message, errors?} body instead of Lumen's HTML pages.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Throwable  $exception
     * @return \Illuminate\Http\JsonResponse
     */
    public function render($request, Throwable $exception)
    {
        if ($exception instanceof ValidationException) {
            return response()->json([
                'message' => 'The given data was invalid.',
                'errors' => $exception->errors(),
            ], 422);
        }

        if ($exception instanceof ModelNotFoundException) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        if ($exception instanceof AuthorizationException) {
            return response()->json(['message' => $exception->getMessage() ?: 'Forbidden.'], 403);
        }

        if ($exception instanceof HttpException) {
            $status = $exception->getStatusCode();

            return response()->json([
                'message' => $exception->getMessage() ?: ($status === 404 ? 'Not found.' : 'HTTP error.'),
            ], $status);
        }

        $payload = ['message' => 'Server error.'];

        if (env('APP_DEBUG', false)) {
            $payload['exception'] = get_class($exception);
            $payload['detail'] = $exception->getMessage();
            $payload['file'] = $exception->getFile().':'.$exception->getLine();
        }

        return response()->json($payload, 500);
    }
}
