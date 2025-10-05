<?php
namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Validation\ValidationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * A list of the exception types that are not reported.
     *
     * @var array<int, class-string<Throwable>>
     */
    protected $dontReport = [
        ValidationException::class,
        AuthenticationException::class,
        AuthorizationException::class,
        ModelNotFoundException::class,
        NotFoundHttpException::class,
    ];

    /**
     * A list of the inputs that are never flashed to the session on validation errors.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Render an exception into an HTTP response.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Throwable  $e
     * @return \Illuminate\Http\JsonResponse|\Symfony\Component\HttpFoundation\Response
     */
    public function render($request, Throwable $e)
    {
        if ($request->expectsJson() || $request->is('api/*')) {
            return $this->handleApiException($request, $e);
        }

        return parent::render($request, $e);
    }

    /**
     * Handle API exceptions with appropriate JSON responses.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Throwable  $e
     * @return \Illuminate\Http\JsonResponse
     */
    protected function handleApiException(Request $request, Throwable $e)
    {
        if ($e instanceof ValidationException) {
            return response()->json([
                'message' => 'The given data was invalid.',
                'errors' => $e->errors(),
            ], 422);
        }

        if ($e instanceof AuthenticationException) {
            Log::warning('Authentication error', [
                'message' => $e->getMessage(),
                'path' => $request->path(),
                'url' => $request->fullUrl(),
            ]);
            return response()->json([
                'message' => 'Unauthenticated. Please provide a valid token.',
            ], 401);
        }

        if ($e instanceof AuthorizationException) {
            Log::warning('Authorization error', [
                'message' => $e->getMessage(),
                'path' => $request->path(),
                'url' => $request->fullUrl(),
            ]);
            return response()->json([
                'message' => 'Forbidden. You do not have permission to perform this action.',
            ], 403);
        }

        if ($e instanceof ModelNotFoundException || $e instanceof NotFoundHttpException) {
            Log::warning('Resource or route not found', [
                'message' => $e->getMessage(),
                'path' => $request->path(),
                'url' => $request->fullUrl(),
            ]);
            return response()->json([
                'message' => 'Resource or route not found.',
            ], 404);
        }

        if ($e instanceof QueryException) {
            Log::error('Database error', [
                'message' => $e->getMessage(),
                'path' => $request->path(),
                'url' => $request->fullUrl(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'message' => 'A database error occurred. Please try again later.',
            ], 500);
        }

        if ($e instanceof HttpException) {
            Log::warning('HTTP error', [
                'message' => $e->getMessage(),
                'status' => $e->getStatusCode(),
                'path' => $request->path(),
                'url' => $request->fullUrl(),
            ]);
            return response()->json([
                'message' => $e->getMessage() ?: 'HTTP error occurred.',
            ], $e->getStatusCode());
        }

        Log::error('Unexpected error', [
            'message' => $e->getMessage(),
            'path' => $request->path(),
            'url' => $request->fullUrl(),
            'trace' => $e->getTraceAsString(),
        ]);
        return response()->json([
            'message' => 'An unexpected error occurred. Please try again later.',
        ], 500);
    }

    /**
     * Convert an authentication exception into an unauthenticated response.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Illuminate\Auth\AuthenticationException  $exception
     * @return \Illuminate\Http\JsonResponse
     */
    protected function unauthenticated($request, AuthenticationException $exception)
    {
        return $this->handleApiException($request, $exception);
    }
}
