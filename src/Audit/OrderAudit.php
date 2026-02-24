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

namespace RenzoJohnson\WcHposHelper\Audit;

use RenzoJohnson\WcHposHelper\Database\DatabaseInterface;
use RenzoJohnson\WcHposHelper\Database\TableResolver;

/**
 * Compares order data between HPOS tables (wp_wc_orders) and legacy
 * posts table (wp_posts) for mismatches in parent_id, customer_id,
 * status, and totals.
 *
 * Only audits type='shop_order' — excludes refunds, drafts, trash.
 * Status is normalized: lowercase, wc- prefix stripped, NULL→''.
 * Totals use decimal-safe ROUND() comparison.
 */
class OrderAudit implements AuditInterface
{
    private int $maxSamples = self::MAX_SAMPLES;
    private int $decimals = 2;

    public function __construct(
        private readonly DatabaseInterface $db,
        private readonly TableResolver $tables,
    ) {}

    public function setMaxSamples(int $limit): void
    {
        $this->maxSamples = $limit;
    }

    public function setDecimals(int $decimals): void
    {
        $this->decimals = $decimals;
    }

    public function run(): AuditResult
    {
        $orders = $this->tables->wcOrders();
        $posts = $this->tables->posts();

        $countSql = "SELECT COUNT(*) FROM {$orders} o "
            . "INNER JOIN {$posts} p ON o.id = p.ID "
            . "WHERE o.type = 'shop_order' AND ("
            . "o.parent_order_id != p.post_parent "
            . "OR o.customer_id != COALESCE(("
            . "SELECT CAST(pm.meta_value AS UNSIGNED) FROM {$this->tables->postmeta()} pm "
            . "WHERE pm.post_id = p.ID AND pm.meta_key = '_customer_user' LIMIT 1), 0) "
            . "OR LOWER(REPLACE(o.status, 'wc-', '')) != LOWER(REPLACE(REPLACE(p.post_status, 'wc-', ''), 'publish', 'completed')) "
            . "OR ROUND(o.total_amount, {$this->decimals}) != ROUND(CAST(("
            . "SELECT pm2.meta_value FROM {$this->tables->postmeta()} pm2 "
            . "WHERE pm2.post_id = p.ID AND pm2.meta_key = '_order_total' LIMIT 1) AS DECIMAL(20, {$this->decimals})), {$this->decimals})"
            . ")";

        $total = (int) $this->db->fetchValue($countSql);

        if ($total === 0) {
            return new AuditResult(
                audit: 'order',
                status: 'passed',
                passed: true,
                checked: $this->getTotalOrders(),
                mismatches: 0,
                warnings: 0,
                samples: [],
                metadata: ['tables' => [$orders, $posts]],
            );
        }

        $sampleSql = "SELECT o.id, o.parent_order_id AS hpos_parent, p.post_parent AS post_parent, "
            . "o.customer_id AS hpos_customer, "
            . "COALESCE((SELECT CAST(pm.meta_value AS UNSIGNED) FROM {$this->tables->postmeta()} pm "
            . "WHERE pm.post_id = p.ID AND pm.meta_key = '_customer_user' LIMIT 1), 0) AS post_customer, "
            . "o.status AS hpos_status, p.post_status AS post_status, "
            . "ROUND(o.total_amount, {$this->decimals}) AS hpos_total, "
            . "ROUND(CAST((SELECT pm2.meta_value FROM {$this->tables->postmeta()} pm2 "
            . "WHERE pm2.post_id = p.ID AND pm2.meta_key = '_order_total' LIMIT 1) AS DECIMAL(20, {$this->decimals})), {$this->decimals}) AS post_total "
            . "FROM {$orders} o "
            . "INNER JOIN {$posts} p ON o.id = p.ID "
            . "WHERE o.type = 'shop_order' AND ("
            . "o.parent_order_id != p.post_parent "
            . "OR o.customer_id != COALESCE(("
            . "SELECT CAST(pm3.meta_value AS UNSIGNED) FROM {$this->tables->postmeta()} pm3 "
            . "WHERE pm3.post_id = p.ID AND pm3.meta_key = '_customer_user' LIMIT 1), 0) "
            . "OR LOWER(REPLACE(o.status, 'wc-', '')) != LOWER(REPLACE(REPLACE(p.post_status, 'wc-', ''), 'publish', 'completed')) "
            . "OR ROUND(o.total_amount, {$this->decimals}) != ROUND(CAST(("
            . "SELECT pm4.meta_value FROM {$this->tables->postmeta()} pm4 "
            . "WHERE pm4.post_id = p.ID AND pm4.meta_key = '_order_total' LIMIT 1) AS DECIMAL(20, {$this->decimals})), {$this->decimals})"
            . ") ORDER BY o.id ASC LIMIT {$this->maxSamples}";

        $samples = $this->db->fetchAll($sampleSql);

        return new AuditResult(
            audit: 'order',
            status: 'failed',
            passed: false,
            checked: $this->getTotalOrders(),
            mismatches: $total,
            warnings: 0,
            samples: $samples,
            metadata: ['tables' => [$orders, $posts], 'decimals' => $this->decimals],
        );
    }

    private function getTotalOrders(): int
    {
        $orders = $this->tables->wcOrders();

        return (int) $this->db->fetchValue(
            "SELECT COUNT(*) FROM {$orders} WHERE type = 'shop_order'"
        );
    }
}
