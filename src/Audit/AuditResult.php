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

/**
 * Immutable result of a single audit run.
 */
readonly class AuditResult
{
    /**
     * @param string $audit      Audit name: 'order' | 'subscription' | 'customer' | 'meta'
     * @param string $status     Result status: 'passed' | 'failed' | 'skipped' | 'error'
     * @param bool   $passed     True if status === 'passed'
     * @param int    $checked    Total rows examined
     * @param int    $mismatches Count of hard-fail problems
     * @param int    $warnings   Count of non-critical issues
     * @param list<array<string, mixed>> $samples Capped sample rows
     * @param array<string, mixed> $metadata Audit-specific context
     */
    public function __construct(
        public string $audit,
        public string $status,
        public bool $passed,
        public int $checked,
        public int $mismatches,
        public int $warnings,
        public array $samples,
        public array $metadata,
    ) {}
}
