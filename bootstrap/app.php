<?php

use App\Http\Middleware\AuthenticateToken;
use App\Http\Middleware\HandleImageUploads;
use App\Http\Middleware\LogIncomingRequest;
use App\Http\Middleware\OptionalAuthenticateToken;
use App\Http\Middleware\ParseFormBody;
use App\Http\Middleware\UnescapedJsonResponse;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull;
use Illuminate\Foundation\Http\Middleware\TrimStrings;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Exceptions\PostTooLargeException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Request values are used exactly as sent (no trimming, "" stays "").
        $middleware->remove([TrimStrings::class, ConvertEmptyStringsToNull::class]);

        $middleware->api(
            prepend: [ParseFormBody::class, LogIncomingRequest::class],
            append: [UnescapedJsonResponse::class],
        );

        $middleware->alias([
            'auth.jwt' => AuthenticateToken::class,
            'auth.jwt.optional' => OptionalAuthenticateToken::class,
            'upload' => HandleImageUploads::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $isApi = fn (Request $request) => $request->is('api', 'api/*');

        $exceptions->shouldRenderJsonWhen(fn (Request $request) => $isApi($request) || $request->expectsJson());

        // Unknown routes / methods.
        $exceptions->render(function (NotFoundHttpException|MethodNotAllowedHttpException $exception, Request $request) use ($isApi) {
            if ($isApi($request)) {
                return response()->json([
                    'success' => false,
                    'message' => "Cannot {$request->method()} {$request->getPathInfo()}",
                ], 404, [], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            }
        });

        // Request body larger than PHP's post_max_size.
        $exceptions->render(function (PostTooLargeException $exception, Request $request) use ($isApi) {
            if ($isApi($request)) {
                return response()->json(['message' => 'Something went wrong!', 'error' => 'request entity too large'], 500);
            }
        });

        // Any other unhandled error.
        $exceptions->render(function (Throwable $exception, Request $request) use ($isApi) {
            $handledElsewhere = $exception instanceof HttpResponseException
                || $exception instanceof HttpExceptionInterface
                || $exception instanceof ValidationException;

            if ($isApi($request) && ! $handledElsewhere) {
                return response()->json(
                    ['message' => 'Something went wrong!', 'error' => $exception->getMessage()],
                    500,
                    [],
                    JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
                );
            }
        });
    })->create();
