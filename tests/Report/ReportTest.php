<?php

declare(strict_types=1);

namespace RenzoJohnson\WcHposHelper\Tests\Report;

use PHPUnit\Framework\TestCase;
use RenzoJohnson\WcHposHelper\Audit\AuditResult;
use RenzoJohnson\WcHposHelper\Report\Report;

class ReportTest extends TestCase
{
    public function testToArrayStructure(): void
    {
        $results = [
            new AuditResult('order', 'passed', true, 100, 0, 0, [], []),
            new AuditResult('subscription', 'failed', false, 50, 3, 1, [], []),
        ];

        $report = new Report('wp_', true, true, $results);
        $array = $report->toArray();

        $this->assertArrayHasKey('generated_at', $array);
        $this->assertArrayHasKey('prefix', $array);
        $this->assertArrayHasKey('hpos_enabled', $array);
        $this->assertArrayHasKey('sync_enabled', $array);
        $this->assertArrayHasKey('summary', $array);
        $this->assertArrayHasKey('results', $array);
        $this->assertSame('wp_', $array['prefix']);
        $this->assertTrue($array['hpos_enabled']);
        $this->assertTrue($array['sync_enabled']);
        $this->assertCount(2, $array['results']);
    }

    public function testToJsonValidOutput(): void
    {
        $results = [
            new AuditResult('order', 'passed', true, 10, 0, 0, [], []),
        ];

        $report = new Report('wp_', true, true, $results);
        $json = $report->toJson();

        $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        $this->assertIsArray($decoded);
        $this->assertSame('wp_', $decoded['prefix']);
    }

    public function testSummaryAggregation(): void
    {
        $results = [
            new AuditResult('order', 'passed', true, 100, 0, 2, [], []),
            new AuditResult('subscription', 'failed', false, 50, 5, 1, [], []),
            new AuditResult('customer', 'skipped', true, 0, 0, 0, [], ['reason' => 'missing tables']),
            new AuditResult('meta', 'passed', true, 200, 0, 0, [], []),
        ];

        $report = new Report('wp_', true, true, $results);

        $this->assertSame(350, $report->summary['total_checked']);
        $this->assertSame(5, $report->summary['total_mismatches']);
        $this->assertSame(3, $report->summary['total_warnings']);
        $this->assertSame(2, $report->summary['audits_passed']);
        $this->assertSame(1, $report->summary['audits_failed']);
        $this->assertSame(1, $report->summary['audits_skipped']);
        $this->assertFalse($report->summary['overall_passed']);
    }

    public function testOverallPassedWhenNoFailures(): void
    {
        $results = [
            new AuditResult('order', 'passed', true, 100, 0, 5, [], []),
            new AuditResult('meta', 'skipped', true, 0, 0, 0, [], []),
        ];

        $report = new Report('wp_', true, false, $results);

        $this->assertTrue($report->summary['overall_passed']);
    }

    public function testGeneratedAtIsUtc(): void
    {
        $results = [
            new AuditResult('order', 'passed', true, 0, 0, 0, [], []),
        ];

        $report = new Report('wp_', true, true, $results);

        $this->assertStringContainsString('+00:00', $report->generatedAt);
    }
}
