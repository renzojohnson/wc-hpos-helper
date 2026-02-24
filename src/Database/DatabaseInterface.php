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

namespace RenzoJohnson\WcHposHelper\Database;

/**
 * Database abstraction for read-only queries.
 *
 * All implementations MUST use prepared statements exclusively.
 * All implementations MUST only execute SELECT and SHOW queries.
 */
interface DatabaseInterface
{
    /**
     * Execute a query and return all rows.
     *
     * @param array<string, mixed> $params
     * @return list<array<string, mixed>>
     */
    public function fetchAll(string $sql, array $params = []): array;

    /**
     * Execute a query and return the first row, or null.
     *
     * @param array<string, mixed> $params
     * @return array<string, mixed>|null
     */
    public function fetchOne(string $sql, array $params = []): ?array;

    /**
     * Execute a query and return the first column of the first row.
     *
     * @param array<string, mixed> $params
     */
    public function fetchValue(string $sql, array $params = []): mixed;

    /**
     * Execute a query and return the number of affected rows.
     *
     * @param array<string, mixed> $params
     */
    public function execute(string $sql, array $params = []): int;
}
