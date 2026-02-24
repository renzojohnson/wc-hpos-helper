<?php

declare(strict_types=1);

namespace RenzoJohnson\WcHposHelper\Exception;

/**
 * Thrown when the database connection fails.
 *
 * Message is generic and NEVER contains DSN, username, or password.
 */
class ConnectionException extends HposException
{
    public function __construct(string $message = 'Failed to connect to database', ?\Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }
}
