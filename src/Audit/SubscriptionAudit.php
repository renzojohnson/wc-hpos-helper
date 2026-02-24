<?php

declare(strict_types=1);

namespace RenzoJohnson\WcHposHelper\Audit;

use RenzoJohnson\WcHposHelper\Database\DatabaseInterface;
use RenzoJohnson\WcHposHelper\Database\TableResolver;

/**
 * Audits WooCommerce Subscriptions stored in HPOS tables.
 *
 * Checks for:
 * - customer_id = 0 on active subscriptions (hard-fail)
 * - parent_order_id = 0 on cancelled/expired subscriptions (warning)
 * - Orphaned renewal orders pointing to non-existent subscriptions
 */
class SubscriptionAudit implements AuditInterface
{
    private const array ACTIVE_STATUSES = [
        'wc-active',
        'wc-pending',
        'wc-on-hold',
    ];

    private const array CANCELLED_STATUSES = [
        'wc-cancelled',
        'wc-expired',
        'wc-pending-cancel',
    ];

    private int $maxSamples = self::MAX_SAMPLES;

    public function __construct(
        private readonly DatabaseInterface $db,
        private readonly TableResolver $tables,
    ) {}

    public function setMaxSamples(int $limit): void
    {
        $this->maxSamples = $limit;
    }

    public function run(): AuditResult
    {
        $orders = $this->tables->wcOrders();
        $postmeta = $this->tables->postmeta();

        $totalSubs = (int) $this->db->fetchValue(
            "SELECT COUNT(*) FROM {$orders} WHERE type = 'shop_subscription'"
        );

        if ($totalSubs === 0) {
            return new AuditResult(
                audit: 'subscription',
                status: 'passed',
                passed: true,
                checked: 0,
                mismatches: 0,
                warnings: 0,
                samples: [],
                metadata: ['tables' => [$orders], 'note' => 'No subscriptions found'],
            );
        }

        $mismatches = 0;
        $warnings = 0;
        $samples = [];

        $activeStatuses = $this->buildPlaceholders(self::ACTIVE_STATUSES, 'as');
        $activeParams = $this->buildParams(self::ACTIVE_STATUSES, 'as');

        $zeroCustomerSql = "SELECT id, status, customer_id, parent_order_id "
            . "FROM {$orders} "
            . "WHERE type = 'shop_subscription' "
            . "AND customer_id = 0 "
            . "AND status IN ({$activeStatuses}) "
            . "ORDER BY id ASC LIMIT {$this->maxSamples}";

        $zeroCustomerRows = $this->db->fetchAll($zeroCustomerSql, $activeParams);
        $zeroCustomerCount = count($zeroCustomerRows);

        if ($zeroCustomerCount >= $this->maxSamples) {
            $zeroCustomerCount = (int) $this->db->fetchValue(
                "SELECT COUNT(*) FROM {$orders} "
                . "WHERE type = 'shop_subscription' AND customer_id = 0 "
                . "AND status IN ({$activeStatuses})",
                $activeParams,
            );
        }

        foreach ($zeroCustomerRows as $row) {
            $row['issue'] = 'customer_id=0 on active subscription';
            $row['severity'] = 'error';
            $samples[] = $row;
        }
        $mismatches += $zeroCustomerCount;

        $cancelledStatuses = $this->buildPlaceholders(self::CANCELLED_STATUSES, 'cs');
        $cancelledParams = $this->buildParams(self::CANCELLED_STATUSES, 'cs');

        $zeroParentSql = "SELECT id, status, customer_id, parent_order_id "
            . "FROM {$orders} "
            . "WHERE type = 'shop_subscription' "
            . "AND parent_order_id = 0 "
            . "AND status IN ({$cancelledStatuses}) "
            . "ORDER BY id ASC LIMIT {$this->maxSamples}";

        $zeroParentRows = $this->db->fetchAll($zeroParentSql, $cancelledParams);

        foreach ($zeroParentRows as $row) {
            $row['issue'] = 'parent_order_id=0 on cancelled subscription';
            $row['severity'] = 'warning';
            $samples[] = $row;
        }
        $warnings += count($zeroParentRows);

        $orphanSql = "SELECT pm.post_id AS renewal_order_id, pm.meta_value AS subscription_id "
            . "FROM {$postmeta} pm "
            . "LEFT JOIN {$orders} o ON o.id = CAST(pm.meta_value AS UNSIGNED) AND o.type = 'shop_subscription' "
            . "WHERE pm.meta_key = '_subscription_renewal' AND o.id IS NULL "
            . "ORDER BY pm.post_id ASC LIMIT {$this->maxSamples}";

        $orphanRows = $this->db->fetchAll($orphanSql);

        foreach ($orphanRows as $row) {
            $row['issue'] = 'orphaned renewal pointing to non-existent subscription';
            $row['severity'] = 'error';
            $samples[] = $row;
        }
        $mismatches += count($orphanRows);

        $samples = array_slice($samples, 0, $this->maxSamples);

        $status = ($mismatches > 0) ? 'failed' : (($warnings > 0) ? 'passed' : 'passed');

        return new AuditResult(
            audit: 'subscription',
            status: $mismatches > 0 ? 'failed' : 'passed',
            passed: $mismatches === 0,
            checked: $totalSubs,
            mismatches: $mismatches,
            warnings: $warnings,
            samples: $samples,
            metadata: [
                'tables' => [$orders, $postmeta],
                'active_statuses' => self::ACTIVE_STATUSES,
                'cancelled_statuses' => self::CANCELLED_STATUSES,
            ],
        );
    }

    /**
     * @param list<string> $values
     */
    private function buildPlaceholders(array $values, string $prefix): string
    {
        $placeholders = [];
        for ($i = 0; $i < count($values); $i++) {
            $placeholders[] = ":{$prefix}{$i}";
        }

        return implode(', ', $placeholders);
    }

    /**
     * @param list<string> $values
     * @return array<string, string>
     */
    private function buildParams(array $values, string $prefix): array
    {
        $params = [];
        for ($i = 0; $i < count($values); $i++) {
            $params["{$prefix}{$i}"] = $values[$i];
        }

        return $params;
    }
}
