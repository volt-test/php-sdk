<?php

namespace VoltTest\Exceptions;

class ErrorHandler
{
    private static bool $registered = false;

    public static function register(): void
    {
        if (self::$registered) {
            return;
        }
        set_error_handler([self::class, 'handleError']);
        set_exception_handler([self::class, 'handleException']);
        register_shutdown_function([self::class, 'handleShutdown']);
        self::$registered = true;
    }

    public static function handleError(int $errno, string $errstr, string $errfile, int $errline): bool
    {
        throw new \ErrorException(
            $errstr,
            $errno,
            0,
            $errfile,
            $errline
        );
    }

    public static function handleException(\Throwable $exception): void
    {
        // Log the exception or handle it as needed
        // You might want to integrate with PSR-3 Logger here
        throw $exception;
    }

    public static function handleShutdown(): void
    {
        $error = error_get_last();
        if ($error !== null && self::isFatalError($error['type'])) {
            self::handleError(
                $error['type'],
                $error['message'],
                $error['file'],
                $error['line']
            );
        }
    }

    private static function isFatalError(int $type): bool
    {
        return in_array($type, [
            E_ERROR,
            E_PARSE,
            E_CORE_ERROR,
            E_COMPILE_ERROR,
            E_USER_ERROR,
        ], true);
    }

    public static function unregister(): void
    {
        if (! self::$registered) {
            return;
        }

        restore_error_handler();
        restore_exception_handler();
        self::$registered = false;
    }
}
