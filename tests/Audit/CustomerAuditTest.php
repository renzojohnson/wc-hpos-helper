<?php

declare(strict_types=1);

namespace RenzoJohnson\WcHposHelper\Tests\Audit;

use PHPUnit\Framework\TestCase;
use RenzoJohnson\WcHposHelper\Audit\CustomerAudit;
use RenzoJohnson\WcHposHelper\Database\DatabaseInterface;
use RenzoJohnson\WcHposHelper\Database\TableResolver;

class CustomerAuditTest extends TestCase
{
    public function testEmailMismatchDetected(): void
    {
        $db = $this->createFakeDb(
            totalCustomers: 10,
            mismatchRows: [
                [
                    'customer_id' => 5,
                    'lookup_email' => 'old@example.com',
                    'order_email' => 'new@example.com',
                    'order_id' => 42,
                ],
            ],
        );
        $audit = new CustomerAudit($db, new TableResolver('wp_'));

        $result = $audit->run();

        $this->assertSame('customer', $result->audit);
        $this->assertSame('failed', $result->status);
        $this->assertFalse($result->passed);
        $this->assertSame(1, $result->mismatches);
    }

    public function testValidCustomerPasses(): void
    {
        $db = $this->createFakeDb(totalCustomers: 10);
        $audit = new CustomerAudit($db, new TableResolver('wp_'));

        $result = $audit->run();

        $this->assertSame('passed', $result->status);
        $this->assertTrue($result->passed);
        $this->assertSame(0, $result->mismatches);
    }

    public function testGuestRowWarning(): void
    {
        $db = $this->createFakeDb(
            totalCustomers: 5,
            guestRows: [
                ['customer_id' => 0, 'lookup_email' => 'guest@example.com'],
            ],
        );
        $audit = new CustomerAudit($db, new TableResolver('wp_'));

        $result = $audit->run();

        $this->assertSame('passed', $result->status);
        $this->assertSame(1, $result->warnings);
        $this->assertSame('warning', $result->samples[0]['severity']);
    }

    public function testMissingEmailWarning(): void
    {
        $db = $this->createFakeDb(
            totalCustomers: 5,
            emptyEmailRows: [
                ['customer_id' => 7, 'lookup_email' => ''],
            ],
        );
        $audit = new CustomerAudit($db, new TableResolver('wp_'));

        $result = $audit->run();

        $this->assertSame('passed', $result->status);
        $this->assertSame(1, $result->warnings);
        $this->assertStringContainsString('staleness', $result->samples[0]['issue']);
    }

    /**
     * @param list<array<string, mixed>> $guestRows
     * @param list<array<string, mixed>> $mismatchRows
     * @param list<array<string, mixed>> $emptyEmailRows
     */
    private function createFakeDb(
        int $totalCustomers,
        array $guestRows = [],
        array $mismatchRows = [],
        array $emptyEmailRows = [],
    ): DatabaseInterface {
        return new class ($totalCustomers, $guestRows, $mismatchRows, $emptyEmailRows) implements DatabaseInterface {
            /** @param list<array<string, mixed>> $guestRows */
            /** @param list<array<string, mixed>> $mismatchRows */
            /** @param list<array<string, mixed>> $emptyEmailRows */
            public function __construct(
                private readonly int $totalCustomers,
                private readonly array $guestRows,
                private readonly array $mismatchRows,
                private readonly array $emptyEmailRows,
            ) {}

            public function fetchAll(string $sql, array $params = []): array
            {
                if (str_contains($sql, 'customer_id = 0')) {
                    return $this->guestRows;
                }

                if (str_contains($sql, 'cl.email != o.billing_email')) {
                    return $this->mismatchRows;
                }

                if (str_contains($sql, "cl.email = ''")) {
                    return $this->emptyEmailRows;
                }

                return [];
            }

            public function fetchOne(string $sql, array $params = []): ?array
            {
                return null;
            }

            public function fetchValue(string $sql, array $params = []): mixed
            {
                if (str_contains($sql, 'COUNT(*)') && str_contains($sql, 'wc_customer_lookup')) {
                    return $this->totalCustomers;
                }

                return count($this->mismatchRows);
            }

            public function execute(string $sql, array $params = []): int
            {
                return 0;
            }
        };
    }
}
