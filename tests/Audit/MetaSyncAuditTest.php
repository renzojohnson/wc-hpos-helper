<?php

declare(strict_types=1);

namespace RenzoJohnson\WcHposHelper\Tests\Audit;

use PHPUnit\Framework\TestCase;
use RenzoJohnson\WcHposHelper\Audit\MetaSyncAudit;
use RenzoJohnson\WcHposHelper\Database\DatabaseInterface;
use RenzoJohnson\WcHposHelper\Database\TableResolver;

class MetaSyncAuditTest extends TestCase
{
    public function testCanonicalKeyMismatchDetected(): void
    {
        $db = $this->createFakeDb(
            checked: 100,
            mismatches: 2,
            missing: 0,
            sampleRows: [
                [
                    'order_id' => 42,
                    'meta_key' => '_billing_email',
                    'hpos_value' => 'john@example.com',
                    'post_value' => 'jane@example.com',
                    'issue' => 'value_mismatch',
                ],
                [
                    'order_id' => 42,
                    'meta_key' => '_billing_phone',
                    'hpos_value' => '555-1234',
                    'post_value' => '555-5678',
                    'issue' => 'value_mismatch',
                ],
            ],
        );
        $audit = new MetaSyncAudit($db, new TableResolver('wp_'));

        $result = $audit->run();

        $this->assertSame('meta', $result->audit);
        $this->assertSame('failed', $result->status);
        $this->assertSame(2, $result->mismatches);
        $this->assertCount(2, $result->samples);
        $this->assertSame('hpos_authoritative', $result->metadata['direction']);
    }

    public function testMissingKeyInPostsDetected(): void
    {
        $db = $this->createFakeDb(
            checked: 50,
            mismatches: 0,
            missing: 3,
            sampleRows: [
                [
                    'order_id' => 10,
                    'meta_key' => '_billing_city',
                    'hpos_value' => 'Orlando',
                    'post_value' => '',
                    'issue' => 'missing_in_posts',
                ],
            ],
        );
        $audit = new MetaSyncAudit($db, new TableResolver('wp_'));

        $result = $audit->run();

        $this->assertSame(3, $result->metadata['missing_in_posts']);
        $this->assertSame(3, $result->warnings);
    }

    public function testNoMismatchesPasses(): void
    {
        $db = $this->createFakeDb(checked: 200, mismatches: 0, missing: 0);
        $audit = new MetaSyncAudit($db, new TableResolver('wp_'));

        $result = $audit->run();

        $this->assertSame('passed', $result->status);
        $this->assertTrue($result->passed);
        $this->assertSame(0, $result->mismatches);
        $this->assertSame(0, $result->warnings);
        $this->assertEmpty($result->samples);
    }

    public function testCanonicalKeysInMetadata(): void
    {
        $db = $this->createFakeDb(checked: 10, mismatches: 0, missing: 0);
        $audit = new MetaSyncAudit($db, new TableResolver('wp_'));

        $result = $audit->run();

        $this->assertArrayHasKey('canonical_keys', $result->metadata);
        $this->assertContains('_billing_email', $result->metadata['canonical_keys']);
        $this->assertContains('_customer_user', $result->metadata['canonical_keys']);
    }

    /**
     * @param list<array<string, mixed>> $sampleRows
     */
    private function createFakeDb(
        int $checked,
        int $mismatches,
        int $missing,
        array $sampleRows = [],
    ): DatabaseInterface {
        return new class ($checked, $mismatches, $missing, $sampleRows) implements DatabaseInterface {
            private int $fetchValueIndex = 0;

            /** @param list<array<string, mixed>> $sampleRows */
            public function __construct(
                private readonly int $checked,
                private readonly int $mismatches,
                private readonly int $missing,
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
                $this->fetchValueIndex++;

                if ($this->fetchValueIndex === 1) {
                    return $this->mismatches;
                }

                if ($this->fetchValueIndex === 2) {
                    return $this->checked;
                }

                if ($this->fetchValueIndex === 3) {
                    return $this->missing;
                }

                return 0;
            }

            public function execute(string $sql, array $params = []): int
            {
                return 0;
            }
        };
    }
}
