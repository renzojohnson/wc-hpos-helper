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

namespace RenzoJohnson\WcHposHelper;

use RenzoJohnson\WcHposHelper\Audit\AuditInterface;
use RenzoJohnson\WcHposHelper\Audit\AuditResult;
use RenzoJohnson\WcHposHelper\Audit\CustomerAudit;
use RenzoJohnson\WcHposHelper\Audit\MetaSyncAudit;
use RenzoJohnson\WcHposHelper\Audit\OrderAudit;
use RenzoJohnson\WcHposHelper\Audit\SubscriptionAudit;
use RenzoJohnson\WcHposHelper\Database\Database;
use RenzoJohnson\WcHposHelper\Database\DatabaseInterface;
use RenzoJohnson\WcHposHelper\Database\TableResolver;
use RenzoJohnson\WcHposHelper\Exception\HposException;
use RenzoJohnson\WcHposHelper\Report\Report;

/**
 * Main entry point for HPOS data integrity audits.
 *
 * Connects to a WooCommerce database and audits orders, subscriptions,
 * customers, and meta data for HPOS migration issues.
 *
 * Connection is lazy — no database connection is made until the first
 * audit method is called. If a DatabaseInterface is provided, the
 * DSN/user/pass parameters are stored but never used.
 */
class HposHelper
{
    private readonly DatabaseInterface $db;
    private readonly TableResolver $tables;
    private readonly string $prefix;
    private readonly string $schema;
    private int $sampleLimit = AuditInterface::MAX_SAMPLES;

    public function __construct(
        string $dsn,
        string $user,
        string $pass,
        ?DatabaseInterface $db = null,
        string $prefix = 'wp_',
    ) {
        $this->prefix = $this->validatePrefix($prefix);
        $this->tables = new TableResolver($this->prefix);
        $this->schema = $this->extractSchema($dsn);
        $this->db = $db ?? new Database($dsn, $user, $pass);
    }

    public function audit(): Report
    {
        $hposEnabled = $this->isHposEnabled();
        $syncEnabled = $this->isSyncEnabled();

        $results = [];

        if (!$hposEnabled) {
            $skipped = new AuditResult(
                audit: 'all',
                status: 'skipped',
                passed: true,
                checked: 0,
                mismatches: 0,
                warnings: 0,
                samples: [],
                metadata: ['reason' => 'HPOS not enabled'],
            );

            return new Report($this->prefix, false, false, [$skipped]);
        }

        $results[] = $this->runAuditWithPreflight(
            new OrderAudit($this->db, $this->tables),
            'order',
            [$this->tables->rawPrefix() . 'wc_orders', $this->tables->rawPrefix() . 'posts'],
            !$syncEnabled ? 'sync disabled, cross-table comparison not meaningful' : null,
        );

        $results[] = $this->runAuditWithPreflight(
            new SubscriptionAudit($this->db, $this->tables),
            'subscription',
            [$this->tables->rawPrefix() . 'wc_orders'],
        );

        $results[] = $this->runAuditWithPreflight(
            new CustomerAudit($this->db, $this->tables),
            'customer',
            [$this->tables->rawPrefix() . 'wc_customer_lookup', $this->tables->rawPrefix() . 'wc_orders'],
        );

        $results[] = $this->runAuditWithPreflight(
            new MetaSyncAudit($this->db, $this->tables),
            'meta',
            [$this->tables->rawPrefix() . 'wc_orders_meta', $this->tables->rawPrefix() . 'postmeta'],
            !$syncEnabled ? 'sync disabled, cross-table comparison not meaningful' : null,
        );

        return new Report($this->prefix, $hposEnabled, $syncEnabled, $results);
    }

    public function auditOrders(): AuditResult
    {
        return $this->runAuditWithPreflight(
            new OrderAudit($this->db, $this->tables),
            'order',
            [$this->tables->rawPrefix() . 'wc_orders', $this->tables->rawPrefix() . 'posts'],
        );
    }

    public function auditSubscriptions(): AuditResult
    {
        return $this->runAuditWithPreflight(
            new SubscriptionAudit($this->db, $this->tables),
            'subscription',
            [$this->tables->rawPrefix() . 'wc_orders'],
        );
    }

    public function auditCustomers(): AuditResult
    {
        return $this->runAuditWithPreflight(
            new CustomerAudit($this->db, $this->tables),
            'customer',
            [$this->tables->rawPrefix() . 'wc_customer_lookup', $this->tables->rawPrefix() . 'wc_orders'],
        );
    }

    public function auditMeta(): AuditResult
    {
        return $this->runAuditWithPreflight(
            new MetaSyncAudit($this->db, $this->tables),
            'meta',
            [$this->tables->rawPrefix() . 'wc_orders_meta', $this->tables->rawPrefix() . 'postmeta'],
        );
    }

    public function setSampleLimit(int $limit): void
    {
        $this->sampleLimit = $limit;
    }

    public function isHposEnabled(): bool
    {
        $options = $this->tables->options();

        $value = $this->db->fetchValue(
            "SELECT option_value FROM {$options} WHERE option_name = :name LIMIT 1",
            ['name' => 'woocommerce_custom_orders_table_enabled'],
        );

        return $value === 'yes';
    }

    public function isSyncEnabled(): bool
    {
        $options = $this->tables->options();

        $value = $this->db->fetchValue(
            "SELECT option_value FROM {$options} WHERE option_name = :name LIMIT 1",
            ['name' => 'woocommerce_custom_orders_table_data_sync_enabled'],
        );

        return $value === 'yes';
    }

    /**
     * @param list<string> $requiredTables Raw table names without backticks
     */
    private function runAuditWithPreflight(
        AuditInterface $audit,
        string $auditName,
        array $requiredTables,
        ?string $skipReason = null,
    ): AuditResult {
        if ($skipReason !== null) {
            return new AuditResult(
                audit: $auditName,
                status: 'skipped',
                passed: true,
                checked: 0,
                mismatches: 0,
                warnings: 0,
                samples: [],
                metadata: ['reason' => $skipReason],
            );
        }

        $missingTables = $this->checkTablesExist($requiredTables);

        if ($missingTables !== []) {
            return new AuditResult(
                audit: $auditName,
                status: 'skipped',
                passed: true,
                checked: 0,
                mismatches: 0,
                warnings: 0,
                samples: [],
                metadata: ['reason' => 'Missing tables', 'missing' => $missingTables],
            );
        }

        if (method_exists($audit, 'setMaxSamples')) {
            $audit->setMaxSamples($this->sampleLimit);
        }

        return $audit->run();
    }

    /**
     * Check which required tables exist in the database.
     *
     * Uses information_schema first, falls back to SHOW TABLES LIKE.
     *
     * @param list<string> $tables Raw table names
     * @return list<string> Missing table names
     */
    private function checkTablesExist(array $tables): array
    {
        $missing = [];

        foreach ($tables as $table) {
            $exists = $this->tableExists($table);

            if (!$exists) {
                $missing[] = $table;
            }
        }

        return $missing;
    }

    private function tableExists(string $table): bool
    {
        if ($this->schema !== '') {
            $result = $this->db->fetchValue(
                'SELECT COUNT(*) FROM information_schema.TABLES '
                . 'WHERE TABLE_SCHEMA = :schema AND TABLE_NAME = :table',
                ['schema' => $this->schema, 'table' => $table],
            );

            return ((int) $result) > 0;
        }

        $rows = $this->db->fetchAll("SHOW TABLES LIKE :table", ['table' => $table]);

        return $rows !== [];
    }

    private function validatePrefix(string $prefix): string
    {
        if ($prefix === '') {
            throw new HposException('Table prefix cannot be empty');
        }

        $normalized = $prefix;

        if (!str_ends_with($normalized, '_')) {
            $normalized .= '_';
        }

        while (str_contains($normalized, '__')) {
            $normalized = str_replace('__', '_', $normalized);
        }

        if (!preg_match('/^[A-Za-z][A-Za-z0-9_]*$/', $normalized)) {
            throw new HposException(
                "Invalid table prefix: '{$prefix}'. Must start with a letter and contain only letters, digits, and underscores."
            );
        }

        return $normalized;
    }

    private function extractSchema(string $dsn): string
    {
        if (preg_match('/dbname=([^;]+)/', $dsn, $matches)) {
            return $matches[1];
        }

        return '';
    }
}
