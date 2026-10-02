<?php

namespace App\Http\Controllers;

use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Throwable;

abstract class Controller
{
    /**
     * 500 response in the API's error format: {success: false, message, error}.
     *
     * @param  array<string, mixed>  $extra  keys placed between message and error
     */
    protected function serverError(string $message, Throwable $exception, array $extra = []): JsonResponse
    {
        report($exception);

        return response()->json(
            ['success' => false, 'message' => $message] + $extra + ['error' => self::errorMessage($exception)],
            500,
        );
    }

    /**
     * Error text safe to return to clients (database errors without the SQL statement).
     */
    protected static function errorMessage(Throwable $exception): string
    {
        if ($exception instanceof QueryException && $exception->getPrevious() !== null) {
            return $exception->getPrevious()->getMessage();
        }

        return $exception->getMessage();
    }
}
