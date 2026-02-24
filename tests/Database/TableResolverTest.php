<?php

declare(strict_types=1);

namespace RenzoJohnson\WcHposHelper\Tests\Database;

use PHPUnit\Framework\TestCase;
use RenzoJohnson\WcHposHelper\Database\TableResolver;

class TableResolverTest extends TestCase
{
    public function testStandardPrefixResolvesCorrectly(): void
    {
        $resolver = new TableResolver('wp_');

        $this->assertSame('`wp_posts`', $resolver->posts());
        $this->assertSame('`wp_postmeta`', $resolver->postmeta());
        $this->assertSame('`wp_wc_orders`', $resolver->wcOrders());
        $this->assertSame('`wp_wc_orders_meta`', $resolver->wcOrdersMeta());
        $this->assertSame('`wp_wc_customer_lookup`', $resolver->wcCustomerLookup());
        $this->assertSame('`wp_options`', $resolver->options());
    }

    public function testMultisitePrefixResolvesCorrectly(): void
    {
        $resolver = new TableResolver('wp_2_');

        $this->assertSame('`wp_2_posts`', $resolver->posts());
        $this->assertSame('`wp_2_wc_orders`', $resolver->wcOrders());
        $this->assertSame('`wp_2_options`', $resolver->options());
    }

    public function testRawPrefixReturnsUnescaped(): void
    {
        $resolver = new TableResolver('wp_');

        $this->assertSame('wp_', $resolver->rawPrefix());
    }

    public function testBacktickInPrefixIsEscaped(): void
    {
        $resolver = new TableResolver('wp`test_');

        $this->assertSame('`wp``test_posts`', $resolver->posts());
    }

    public function testCustomTableName(): void
    {
        $resolver = new TableResolver('custom_');

        $this->assertSame('`custom_my_table`', $resolver->table('my_table'));
    }
}
