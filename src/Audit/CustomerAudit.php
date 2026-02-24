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
 * Audits wp_wc_customer_lookup against wp_wc_orders billing data.
 *
 * Compares customer_id and email semantically. Guests (customer_id=0)
 * are skipped with a warning if a lookup row exists. Missing email
 * in lookup is a warning (staleness). Email mismatch is an error.
 *
 * Note: wp_wc_customer_lookup is analytics-oriented and may lag.
 */
class CustomerAudit implements AuditInterface
{
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
        $lookup = $this->tables->wcCustomerLookup();
        $orders = $this->tables->wcOrders();

        $totalCustomers = (int) $this->db->fetchValue(
            "SELECT COUNT(*) FROM {$lookup}"
        );

        if ($totalCustomers === 0) {
            return new AuditResult(
                audit: 'customer',
                status: 'passed',
                passed: true,
                checked: 0,
                mismatches: 0,
                warnings: 0,
                samples: [],
                metadata: ['tables' => [$lookup, $orders], 'note' => 'No customer lookup records'],
            );
        }

        $mismatches = 0;
        $warnings = 0;
        $samples = [];

        $guestSql = "SELECT cl.customer_id, cl.email AS lookup_email "
            . "FROM {$lookup} cl "
            . "WHERE cl.customer_id = 0 "
            . "ORDER BY cl.customer_id ASC LIMIT {$this->maxSamples}";

        $guestRows = $this->db->fetchAll($guestSql);
        foreach ($guestRows as $row) {
            $row['issue'] = 'lookup row exists for guest (customer_id=0)';
            $row['severity'] = 'warning';
            $samples[] = $row;
        }
        $warnings += count($guestRows);

        $mismatchSql = "SELECT cl.customer_id, cl.email AS lookup_email, "
            . "o.billing_email AS order_email, o.id AS order_id "
            . "FROM {$lookup} cl "
            . "INNER JOIN {$orders} o ON o.customer_id = cl.customer_id "
            . "WHERE cl.customer_id > 0 "
            . "AND cl.email != '' "
            . "AND o.billing_email != '' "
            . "AND cl.email != o.billing_email "
            . "AND o.type = 'shop_order' "
            . "ORDER BY cl.customer_id ASC LIMIT {$this->maxSamples}";

        $mismatchRows = $this->db->fetchAll($mismatchSql);
        $mismatchCount = count($mismatchRows);

        if ($mismatchCount >= $this->maxSamples) {
            $mismatchCount = (int) $this->db->fetchValue(
                "SELECT COUNT(DISTINCT cl.customer_id) "
                . "FROM {$lookup} cl "
                . "INNER JOIN {$orders} o ON o.customer_id = cl.customer_id "
                . "WHERE cl.customer_id > 0 "
                . "AND cl.email != '' "
                . "AND o.billing_email != '' "
                . "AND cl.email != o.billing_email "
                . "AND o.type = 'shop_order'"
            );
        }

        foreach ($mismatchRows as $row) {
            $row['issue'] = 'email mismatch between lookup and order billing';
            $row['severity'] = 'error';
            $samples[] = $row;
        }
        $mismatches += $mismatchCount;

        $emptyEmailSql = "SELECT cl.customer_id, cl.email AS lookup_email "
            . "FROM {$lookup} cl "
            . "WHERE cl.customer_id > 0 AND (cl.email = '' OR cl.email IS NULL) "
            . "ORDER BY cl.customer_id ASC LIMIT {$this->maxSamples}";

        $emptyEmailRows = $this->db->fetchAll($emptyEmailSql);
        foreach ($emptyEmailRows as $row) {
            $row['issue'] = 'missing email in customer lookup (staleness)';
            $row['severity'] = 'warning';
            $samples[] = $row;
        }
        $warnings += count($emptyEmailRows);

        $samples = array_slice($samples, 0, $this->maxSamples);

        return new AuditResult(
            audit: 'customer',
            status: $mismatches > 0 ? 'failed' : 'passed',
            passed: $mismatches === 0,
            checked: $totalCustomers,
            mismatches: $mismatches,
            warnings: $warnings,
            samples: $samples,
            metadata: ['tables' => [$lookup, $orders]],
        );
    }
}
