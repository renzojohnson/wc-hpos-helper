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

namespace RenzoJohnson\WcHposHelper\Tests\Exception;

use PHPUnit\Framework\TestCase;
use RenzoJohnson\WcHposHelper\Exception\QueryException;

class QueryExceptionTest extends TestCase
{
    public function testGettersReturnCorrectValues(): void
    {
        $exception = new QueryException(
            'Column not found',
            sqlState: '42S22',
            driverCode: 1054,
            queryContext: 'SELECT * FROM `wp_wc_orders` WHERE id = :id',
        );

        $this->assertSame('42S22', $exception->getSqlState());
        $this->assertSame(1054, $exception->getDriverCode());
        $this->assertSame('SELECT * FROM `wp_wc_orders` WHERE id = :id', $exception->getQueryContext());
        $this->assertSame('Column not found', $exception->getMessage());
    }

    public function testQueryContextContainsPlaceholdersNotValues(): void
    {
        $exception = new QueryException(
            'Error',
            sqlState: 'HY000',
            driverCode: 0,
            queryContext: 'SELECT option_value FROM `wp_options` WHERE option_name = :name',
        );

        $this->assertStringContainsString(':name', $exception->getQueryContext());
        $this->assertStringNotContainsString('woocommerce_custom', $exception->getQueryContext());
    }

    public function testPreviousExceptionPreserved(): void
    {
        $previous = new \RuntimeException('PDO error');
        $exception = new QueryException(
            'Query failed',
            sqlState: 'HY000',
            driverCode: 0,
            queryContext: 'SELECT 1',
            previous: $previous,
        );

        $this->assertSame($previous, $exception->getPrevious());
    }
}
