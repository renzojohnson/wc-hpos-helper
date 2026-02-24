<?php

declare(strict_types=1);

namespace RenzoJohnson\WcHposHelper\Audit;

use RenzoJohnson\WcHposHelper\Database\DatabaseInterface;
use RenzoJohnson\WcHposHelper\Database\TableResolver;

/**
 * Compares canonical meta keys between wp_wc_orders_meta (HPOS)
 * and wp_postmeta (legacy).
 *
 * HPOS is authoritative (source of truth). Reports when posts-table
 * value differs from HPOS value. Only checks a defined set of canonical
 * keys. Value normalization: TRIM, case-sensitive, NULL→''.
 */
class MetaSyncAudit implements AuditInterface
{
    private const array CANONICAL_KEYS = [
        '_billing_email',
        '_billing_phone',
        '_billing_first_name',
        '_billing_last_name',
        '_billing_address_1',
        '_billing_city',
        '_billing_state',
        '_billing_postcode',
        '_billing_country',
        '_order_total',
        '_payment_method',
        '_customer_user',
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
        $ordersMeta = $this->tables->wcOrdersMeta();
        $postmeta = $this->tables->postmeta();

        $keyPlaceholders = $this->buildKeyPlaceholders();
        $keyParams = $this->buildKeyParams();

        $countSql = "SELECT COUNT(*) FROM {$ordersMeta} om "
            . "INNER JOIN {$postmeta} pm ON om.order_id = pm.post_id AND om.meta_key = pm.meta_key "
            . "WHERE om.meta_key IN ({$keyPlaceholders}) "
            . "AND COALESCE(TRIM(om.meta_value), '') != COALESCE(TRIM(pm.meta_value), '')";

        $total = (int) $this->db->fetchValue($countSql, $keyParams);

        $checkedSql = "SELECT COUNT(*) FROM {$ordersMeta} WHERE meta_key IN ({$keyPlaceholders})";
        $checked = (int) $this->db->fetchValue($checkedSql, $keyParams);

        $missingSql = "SELECT COUNT(*) FROM {$ordersMeta} om "
            . "LEFT JOIN {$postmeta} pm ON om.order_id = pm.post_id AND om.meta_key = pm.meta_key "
            . "WHERE om.meta_key IN ({$keyPlaceholders}) AND pm.meta_id IS NULL";

        $missing = (int) $this->db->fetchValue($missingSql, $keyParams);

        $warningCount = $missing;
        $mismatchCount = $total;

        if ($total === 0 && $missing === 0) {
            return new AuditResult(
                audit: 'meta',
                status: 'passed',
                passed: true,
                checked: $checked,
                mismatches: 0,
                warnings: 0,
                samples: [],
                metadata: [
                    'tables' => [$ordersMeta, $postmeta],
                    'canonical_keys' => self::CANONICAL_KEYS,
                    'direction' => 'hpos_authoritative',
                ],
            );
        }

        $sampleSql = "SELECT om.order_id, om.meta_key, "
            . "COALESCE(TRIM(om.meta_value), '') AS hpos_value, "
            . "COALESCE(TRIM(pm.meta_value), '') AS post_value, "
            . "CASE WHEN pm.meta_id IS NULL THEN 'missing_in_posts' ELSE 'value_mismatch' END AS issue "
            . "FROM {$ordersMeta} om "
            . "LEFT JOIN {$postmeta} pm ON om.order_id = pm.post_id AND om.meta_key = pm.meta_key "
            . "WHERE om.meta_key IN ({$keyPlaceholders}) "
            . "AND (pm.meta_id IS NULL OR COALESCE(TRIM(om.meta_value), '') != COALESCE(TRIM(pm.meta_value), '')) "
            . "ORDER BY om.order_id ASC, om.meta_key ASC LIMIT {$this->maxSamples}";

        $samples = $this->db->fetchAll($sampleSql, $keyParams);

        return new AuditResult(
            audit: 'meta',
            status: $mismatchCount > 0 ? 'failed' : 'passed',
            passed: $mismatchCount === 0,
            checked: $checked,
            mismatches: $mismatchCount,
            warnings: $warningCount,
            samples: $samples,
            metadata: [
                'tables' => [$ordersMeta, $postmeta],
                'canonical_keys' => self::CANONICAL_KEYS,
                'direction' => 'hpos_authoritative',
                'missing_in_posts' => $missing,
            ],
        );
    }

    private function buildKeyPlaceholders(): string
    {
        $placeholders = [];
        for ($i = 0; $i < count(self::CANONICAL_KEYS); $i++) {
            $placeholders[] = ":key{$i}";
        }

        return implode(', ', $placeholders);
    }

    /**
     * @return array<string, string>
     */
    private function buildKeyParams(): array
    {
        $params = [];
        for ($i = 0; $i < count(self::CANONICAL_KEYS); $i++) {
            $params["key{$i}"] = self::CANONICAL_KEYS[$i];
        }

        return $params;
    }
}
