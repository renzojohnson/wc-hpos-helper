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

namespace RenzoJohnson\WcHposHelper\Tests\Audit;

use PHPUnit\Framework\TestCase;
use RenzoJohnson\WcHposHelper\Audit\OrderAudit;
use RenzoJohnson\WcHposHelper\Database\DatabaseInterface;
use RenzoJohnson\WcHposHelper\Database\TableResolver;

class OrderAuditTest extends TestCase
{
    public function testNoMismatchesPasses(): void
    {
        $db = $this->createFakeDb(totalOrders: 10, mismatches: 0);
        $audit = new OrderAudit($db, new TableResolver('wp_'));

        $result = $audit->run();

        $this->assertSame('order', $result->audit);
        $this->assertSame('passed', $result->status);
        $this->assertTrue($result->passed);
        $this->assertSame(10, $result->checked);
        $this->assertSame(0, $result->mismatches);
    }

    public function testMismatchesDetected(): void
    {
        $db = $this->createFakeDb(totalOrders: 100, mismatches: 3, sampleRows: [
            ['id' => 42, 'hpos_parent' => 10, 'post_parent' => 0, 'hpos_status' => 'processing', 'post_status' => 'wc-processing'],
            ['id' => 55, 'hpos_parent' => 0, 'post_parent' => 5, 'hpos_status' => 'completed', 'post_status' => 'completed'],
            ['id' => 99, 'hpos_parent' => 1, 'post_parent' => 1, 'hpos_status' => 'on-hold', 'post_status' => 'wc-on-hold'],
        ]);
        $audit = new OrderAudit($db, new TableResolver('wp_'));

        $result = $audit->run();

        $this->assertSame('failed', $result->status);
        $this->assertFalse($result->passed);
        $this->assertSame(3, $result->mismatches);
        $this->assertCount(3, $result->samples);
    }

    public function testSampleTruncation(): void
    {
        $samples = [];
        for ($i = 0; $i < 50; $i++) {
            $samples[] = ['id' => $i + 1, 'hpos_parent' => 0, 'post_parent' => 1];
        }

        $db = $this->createFakeDb(totalOrders: 500, mismatches: 200, sampleRows: $samples);
        $audit = new OrderAudit($db, new TableResolver('wp_'));

        $result = $audit->run();

        $this->assertSame(200, $result->mismatches);
        $this->assertCount(50, $result->samples);
        $this->assertSame(500, $result->checked);
    }

    public function testDecimalPrecisionInMetadata(): void
    {
        $db = $this->createFakeDb(totalOrders: 5, mismatches: 0);
        $audit = new OrderAudit($db, new TableResolver('wp_'));
        $audit->setDecimals(0);

        $result = $audit->run();

        $this->assertSame('passed', $result->status);
    }

    /**
     * @param list<array<string, mixed>> $sampleRows
     */
    private function createFakeDb(int $totalOrders, int $mismatches, array $sampleRows = []): DatabaseInterface
    {
        return new class ($totalOrders, $mismatches, $sampleRows) implements DatabaseInterface {
            private int $fetchValueCallIndex = 0;

            /** @param list<array<string, mixed>> $sampleRows */
            public function __construct(
                private readonly int $totalOrders,
                private readonly int $mismatches,
                private readonly array $sampleRows,
            ) {}

            public function fetchAll(string $sql, array $params = []): array
            {
                return $this->sampleRows;
            }

            public function fetchOne(string $sql, array $params = []): ?array
            {
                return null;
            }

            public function fetchValue(string $sql, array $params = []): mixed
            {
                $this->fetchValueCallIndex++;

                if (str_contains($sql, "type = 'shop_order'") && !str_contains($sql, 'INNER JOIN')) {
                    return $this->totalOrders;
                }

                return $this->mismatches;
            }

            public function execute(string $sql, array $params = []): int
            {
                return 0;
            }
        };
    }
}
