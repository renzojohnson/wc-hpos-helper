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

namespace RenzoJohnson\WcHposHelper\Report;

use RenzoJohnson\WcHposHelper\Audit\AuditResult;

/**
 * Aggregated report of all audit results.
 *
 * The overall_passed flag is true when zero audits have status 'failed'.
 * Skipped audits do not count as failures. Warnings do not affect passed.
 */
class Report
{
    public readonly string $generatedAt;

    /** @var array<string, mixed> */
    public readonly array $summary;

    /**
     * @param list<AuditResult> $results
     */
    public function __construct(
        public readonly string $prefix,
        public readonly bool $hposEnabled,
        public readonly bool $syncEnabled,
        public readonly array $results,
    ) {
        $this->generatedAt = gmdate('Y-m-d\TH:i:s+00:00');
        $this->summary = $this->buildSummary();
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $resultsArray = [];
        foreach ($this->results as $result) {
            $resultsArray[] = [
                'audit' => $result->audit,
                'status' => $result->status,
                'passed' => $result->passed,
                'checked' => $result->checked,
                'mismatches' => $result->mismatches,
                'warnings' => $result->warnings,
                'samples' => $result->samples,
                'metadata' => $result->metadata,
            ];
        }

        return [
            'generated_at' => $this->generatedAt,
            'prefix' => $this->prefix,
            'hpos_enabled' => $this->hposEnabled,
            'sync_enabled' => $this->syncEnabled,
            'summary' => $this->summary,
            'results' => $resultsArray,
        ];
    }

    public function toJson(): string
    {
        return json_encode(
            $this->toArray(),
            JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function buildSummary(): array
    {
        $totalChecked = 0;
        $totalMismatches = 0;
        $totalWarnings = 0;
        $auditsPassed = 0;
        $auditsFailed = 0;
        $auditsSkipped = 0;

        foreach ($this->results as $result) {
            $totalChecked += $result->checked;
            $totalMismatches += $result->mismatches;
            $totalWarnings += $result->warnings;

            match ($result->status) {
                'passed' => $auditsPassed++,
                'failed' => $auditsFailed++,
                'skipped' => $auditsSkipped++,
                'error' => $auditsFailed++,
                default => null,
            };
        }

        return [
            'total_checked' => $totalChecked,
            'total_mismatches' => $totalMismatches,
            'total_warnings' => $totalWarnings,
            'audits_passed' => $auditsPassed,
            'audits_failed' => $auditsFailed,
            'audits_skipped' => $auditsSkipped,
            'overall_passed' => $auditsFailed === 0,
        ];
    }
}
