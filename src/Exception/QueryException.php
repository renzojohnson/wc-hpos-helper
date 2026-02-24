<?php

/**
 * WC HPOS Helper
 *
 * @package   RenzoJohnson\WcHposHelper
 * @author    Renzo Johnson <hello@renzojohnson.com>
 * @copyright 2026 Renzo Johnson
 * @license   MIT
 * @link      https://renzojohnson.com
 */

declare(strict_types=1);

namespace RenzoJohnson\WcHposHelper\Exception;

/**
 * Thrown when a database query fails.
 *
 * Contains SQL state, driver error code, and query context (SQL template
 * with placeholders). NEVER contains actual parameter values or credentials.
 */
class QueryException extends HposException
{
    public function __construct(
        string $message,
        private readonly string $sqlState,
        private readonly int $driverCode,
        private readonly string $queryContext,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public function getSqlState(): string
    {
        return $this->sqlState;
    }

    public function getDriverCode(): int
    {
        return $this->driverCode;
    }

    /**
     * SQL template with placeholder params. Never contains actual values.
     */
    public function getQueryContext(): string
    {
        return $this->queryContext;
    }
}
