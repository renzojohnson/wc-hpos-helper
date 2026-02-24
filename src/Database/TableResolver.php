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

namespace RenzoJohnson\WcHposHelper\Database;

/**
 * Prefix-safe table name builder with backtick escaping.
 *
 * All table name access MUST go through this class to prevent
 * SQL injection via table prefix manipulation.
 */
class TableResolver
{
    public function __construct(private readonly string $prefix)
    {
    }

    /**
     * Return backtick-escaped table name with prefix.
     */
    public function table(string $name): string
    {
        return '`' . str_replace('`', '``', $this->prefix . $name) . '`';
    }

    public function posts(): string
    {
        return $this->table('posts');
    }

    public function postmeta(): string
    {
        return $this->table('postmeta');
    }

    public function wcOrders(): string
    {
        return $this->table('wc_orders');
    }

    public function wcOrdersMeta(): string
    {
        return $this->table('wc_orders_meta');
    }

    public function wcCustomerLookup(): string
    {
        return $this->table('wc_customer_lookup');
    }

    public function options(): string
    {
        return $this->table('options');
    }

    /**
     * Raw prefix without backticks for LIKE queries.
     */
    public function rawPrefix(): string
    {
        return $this->prefix;
    }
}
