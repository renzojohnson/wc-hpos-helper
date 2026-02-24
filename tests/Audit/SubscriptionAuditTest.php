<?php

declare(strict_types=1);

namespace RenzoJohnson\WcHposHelper\Tests\Audit;

use PHPUnit\Framework\TestCase;
use RenzoJohnson\WcHposHelper\Audit\SubscriptionAudit;
use RenzoJohnson\WcHposHelper\Database\DatabaseInterface;
use RenzoJohnson\WcHposHelper\Database\TableResolver;

class SubscriptionAuditTest extends TestCase
{
    public function testZeroCustomerIdOnActiveIsHardFail(): void
    {
        $db = $this->createFakeDb(
            totalSubs: 5,
            zeroCustomerRows: [
                ['id' => 100, 'status' => 'wc-active', 'customer_id' => 0, 'parent_order_id' => 50],
            ],
        );
        $audit = new SubscriptionAudit($db, new TableResolver('wp_'));

        $result = $audit->run();

        $this->assertSame('subscription', $result->audit);
        $this->assertSame('failed', $result->status);
        $this->assertFalse($result->passed);
        $this->assertSame(1, $result->mismatches);
        $this->assertSame('error', $result->samples[0]['severity']);
    }

    public function testZeroParentOnCancelledIsWarning(): void
    {
        $db = $this->createFakeDb(
            totalSubs: 5,
            zeroParentRows: [
                ['id' => 200, 'status' => 'wc-cancelled', 'customer_id' => 10, 'parent_order_id' => 0],
            ],
        );
        $audit = new SubscriptionAudit($db, new TableResolver('wp_'));

        $result = $audit->run();

        $this->assertSame('passed', $result->status);
        $this->assertTrue($result->passed);
        $this->assertSame(0, $result->mismatches);
        $this->assertSame(1, $result->warnings);
        $this->assertSame('warning', $result->samples[0]['severity']);
    }

    public function testOrphanedRenewalDetected(): void
    {
        $db = $this->createFakeDb(
            totalSubs: 5,
            orphanRows: [
                ['renewal_order_id' => 300, 'subscription_id' => '999'],
            ],
        );
        $audit = new SubscriptionAudit($db, new TableResolver('wp_'));

        $result = $audit->run();

        $this->assertSame('failed', $result->status);
        $this->assertSame(1, $result->mismatches);
        $this->assertStringContainsString('orphaned', $result->samples[0]['issue']);
    }

    public function testNoIssuesPasses(): void
    {
        $db = $this->createFakeDb(totalSubs: 10);
        $audit = new SubscriptionAudit($db, new TableResolver('wp_'));

        $result = $audit->run();

        $this->assertSame('passed', $result->status);
        $this->assertTrue($result->passed);
        $this->assertSame(0, $result->mismatches);
        $this->assertSame(0, $result->warnings);
    }

    public function testNoSubscriptionsFoundPasses(): void
    {
        $db = $this->createFakeDb(totalSubs: 0);
        $audit = new SubscriptionAudit($db, new TableResolver('wp_'));

        $result = $audit->run();

        $this->assertSame('passed', $result->status);
        $this->assertSame(0, $result->checked);
    }

    /**
     * @param list<array<string, mixed>> $zeroCustomerRows
     * @param list<array<string, mixed>> $zeroParentRows
     * @param list<array<string, mixed>> $orphanRows
     */
    private function createFakeDb(
        int $totalSubs,
        array $zeroCustomerRows = [],
        array $zeroParentRows = [],
        array $orphanRows = [],
    ): DatabaseInterface {
        return new class ($totalSubs, $zeroCustomerRows, $zeroParentRows, $orphanRows) implements DatabaseInterface {
            private int $fetchAllCallIndex = 0;

            /** @param list<array<string, mixed>> $zeroCustomerRows */
            /** @param list<array<string, mixed>> $zeroParentRows */
            /** @param list<array<string, mixed>> $orphanRows */
            public function __construct(
                private readonly int $totalSubs,
                private readonly array $zeroCustomerRows,
                private readonly array $zeroParentRows,
                private readonly array $orphanRows,
            ) {}

            public function fetchAll(string $sql, array $params = []): array
            {
                $this->fetchAllCallIndex++;

                if (str_contains($sql, 'customer_id = 0')) {
                    return $this->zeroCustomerRows;
                }

                if (str_contains($sql, 'parent_order_id = 0')) {
                    return $this->zeroParentRows;
                }

                if (str_contains($sql, '_subscription_renewal')) {
                    return $this->orphanRows;
                }

                return [];
            }

            public function fetchOne(string $sql, array $params = []): ?array
            {
                return null;
            }

            public function fetchValue(string $sql, array $params = []): mixed
            {
                if (str_contains($sql, 'shop_subscription')) {
                    return $this->totalSubs;
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
