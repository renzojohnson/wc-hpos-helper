<?php

declare(strict_types=1);

namespace RenzoJohnson\WcHposHelper\Tests;

use PHPUnit\Framework\TestCase;
use RenzoJohnson\WcHposHelper\Database\DatabaseInterface;
use RenzoJohnson\WcHposHelper\Exception\HposException;
use RenzoJohnson\WcHposHelper\HposHelper;

class HposHelperTest extends TestCase
{
    public function testDefaultPrefix(): void
    {
        $db = $this->createFakeDb([]);
        $helper = new HposHelper('mysql:host=localhost;dbname=test', '', '', $db);

        $report = $helper->audit();
        $this->assertSame('wp_', $report->prefix);
    }

    public function testCustomPrefix(): void
    {
        $db = $this->createFakeDb([]);
        $helper = new HposHelper('mysql:host=localhost;dbname=test', '', '', $db, 'mysite_');

        $report = $helper->audit();
        $this->assertSame('mysite_', $report->prefix);
    }

    public function testInvalidPrefixLeadingDigitThrows(): void
    {
        $this->expectException(HposException::class);

        $db = $this->createFakeDb([]);
        new HposHelper('mysql:host=localhost;dbname=test', '', '', $db, '2wp_');
    }

    public function testInvalidPrefixSpecialCharsThrows(): void
    {
        $this->expectException(HposException::class);

        $db = $this->createFakeDb([]);
        new HposHelper('mysql:host=localhost;dbname=test', '', '', $db, 'wp-site_');
    }

    public function testEmptyPrefixThrows(): void
    {
        $this->expectException(HposException::class);

        $db = $this->createFakeDb([]);
        new HposHelper('mysql:host=localhost;dbname=test', '', '', $db, '');
    }

    public function testTrailingUnderscoreEnforced(): void
    {
        $db = $this->createFakeDb([]);
        $helper = new HposHelper('mysql:host=localhost;dbname=test', '', '', $db, 'wp');

        $report = $helper->audit();
        $this->assertSame('wp_', $report->prefix);
    }

    public function testDoubleUnderscoreNormalized(): void
    {
        $db = $this->createFakeDb([]);
        $helper = new HposHelper('mysql:host=localhost;dbname=test', '', '', $db, 'wp__');

        $report = $helper->audit();
        $this->assertSame('wp_', $report->prefix);
    }

    public function testDbInjectionUsesProvidedDb(): void
    {
        $callCount = 0;
        $db = new class ($callCount) implements DatabaseInterface {
            public function __construct(private int &$callCount) {}

            public function fetchAll(string $sql, array $params = []): array
            {
                $this->callCount++;
                return [];
            }

            public function fetchOne(string $sql, array $params = []): ?array
            {
                $this->callCount++;
                return null;
            }

            public function fetchValue(string $sql, array $params = []): mixed
            {
                $this->callCount++;
                return null;
            }

            public function execute(string $sql, array $params = []): int
            {
                $this->callCount++;
                return 0;
            }
        };

        $helper = new HposHelper('invalid:dsn', 'bad', 'bad', $db);
        $helper->audit();

        $this->assertGreaterThan(0, $callCount, 'Injected DB should have been called');
    }

    public function testHposDisabledReturnsSkipped(): void
    {
        $db = $this->createFakeDb([]);
        $helper = new HposHelper('mysql:host=localhost;dbname=test', '', '', $db);

        $report = $helper->audit();

        $this->assertFalse($report->hposEnabled);
        $this->assertCount(1, $report->results);
        $this->assertSame('skipped', $report->results[0]->status);
        $this->assertSame('HPOS not enabled', $report->results[0]->metadata['reason']);
    }

    public function testMissingTablesReturnSkipped(): void
    {
        $db = $this->createFakeDb([
            'woocommerce_custom_orders_table_enabled' => 'yes',
            'woocommerce_custom_orders_table_data_sync_enabled' => 'yes',
        ]);
        $helper = new HposHelper('mysql:host=localhost;dbname=test', '', '', $db);

        $result = $helper->auditOrders();

        $this->assertSame('skipped', $result->status);
        $this->assertArrayHasKey('missing', $result->metadata);
    }

    /**
     * @param array<string, string> $options
     */
    private function createFakeDb(array $options): DatabaseInterface
    {
        return new class ($options) implements DatabaseInterface {
            /** @param array<string, string> $options */
            public function __construct(private readonly array $options) {}

            public function fetchAll(string $sql, array $params = []): array
            {
                return [];
            }

            public function fetchOne(string $sql, array $params = []): ?array
            {
                return null;
            }

            public function fetchValue(string $sql, array $params = []): mixed
            {
                if (str_contains($sql, 'option_value') && isset($params['name'])) {
                    return $this->options[$params['name']] ?? null;
                }

                if (str_contains($sql, 'information_schema') || str_contains($sql, 'COUNT(*)')) {
                    return 0;
                }

                return null;
            }

            public function execute(string $sql, array $params = []): int
            {
                return 0;
            }
        };
    }
}
