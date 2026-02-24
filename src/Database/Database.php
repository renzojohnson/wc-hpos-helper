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

use RenzoJohnson\WcHposHelper\Exception\ConnectionException;
use RenzoJohnson\WcHposHelper\Exception\QueryException;

/**
 * PDO-based database implementation with lazy connection.
 *
 * This class ONLY executes SELECT and SHOW queries. It will never
 * modify data in the database. Connection is established on the
 * first query, not in the constructor.
 */
class Database implements DatabaseInterface
{
    private ?\PDO $pdo = null;

    public function __construct(
        private readonly string $dsn,
        private readonly string $user,
        private readonly string $pass,
    ) {}

    public function fetchAll(string $sql, array $params = []): array
    {
        $stmt = $this->query($sql, $params);
        $result = $stmt->fetchAll();

        return $result !== false ? $result : [];
    }

    public function fetchOne(string $sql, array $params = []): ?array
    {
        $stmt = $this->query($sql, $params);
        $row = $stmt->fetch();

        return $row !== false ? $row : null;
    }

    public function fetchValue(string $sql, array $params = []): mixed
    {
        $stmt = $this->query($sql, $params);

        return $stmt->fetchColumn();
    }

    public function execute(string $sql, array $params = []): int
    {
        $stmt = $this->query($sql, $params);

        return $stmt->rowCount();
    }

    private function connect(): \PDO
    {
        if ($this->pdo !== null) {
            return $this->pdo;
        }

        try {
            $this->pdo = new \PDO($this->dsn, $this->user, $this->pass, [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                \PDO::ATTR_EMULATE_PREPARES => false,
                \PDO::ATTR_STRINGIFY_FETCHES => false,
            ]);

            $this->pdo->exec('SET NAMES utf8mb4');
        } catch (\PDOException $e) {
            throw new ConnectionException(
                'Failed to connect to database',
                previous: $e,
            );
        }

        return $this->pdo;
    }

    private function query(string $sql, array $params): \PDOStatement
    {
        $pdo = $this->connect();

        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);

            return $stmt;
        } catch (\PDOException $e) {
            throw new QueryException(
                $e->getMessage(),
                sqlState: $e->errorInfo[0] ?? 'HY000',
                driverCode: (int) ($e->errorInfo[1] ?? 0),
                queryContext: $sql,
                previous: $e,
            );
        }
    }
}
