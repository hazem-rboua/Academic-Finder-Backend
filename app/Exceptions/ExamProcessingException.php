<?php

namespace App\Exceptions;

use Exception;

class ExamProcessingException extends Exception
{
    /**
     * Create a new exception instance for exam not found
     */
    public static function notFound(string $message): self
    {
        return new self($message, 404);
    }

    /**
     * Create a new exception instance for invalid data
     */
    public static function invalidData(string $message): self
    {
        return new self($message, 422);
    }

    /**
     * Create a new exception instance for server errors
     */
    public static function serverError(string $message): self
    {
        return new self($message, 500);
    }
}

