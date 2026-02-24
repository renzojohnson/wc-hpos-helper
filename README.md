# WC HPOS Helper

[![Latest Version](https://img.shields.io/packagist/v/renzojohnson/wc-hpos-helper.svg)](https://packagist.org/packages/renzojohnson/wc-hpos-helper)
[![PHP Version](https://img.shields.io/packagist/php-v/renzojohnson/wc-hpos-helper.svg)](https://packagist.org/packages/renzojohnson/wc-hpos-helper)
[![License](https://img.shields.io/packagist/l/renzojohnson/wc-hpos-helper.svg)](https://github.com/renzojohnson/wc-hpos-helper/blob/main/LICENSE)

WooCommerce HPOS data integrity toolkit. Audit orders, subscriptions, and customer data after High-Performance Order Storage migration. Detect orphaned records, zero-value fields, and sync mismatches.

**Author:** [Renzo Johnson](https://renzojohnson.com)

## Requirements

- PHP 8.4+
- PDO MySQL extension
- JSON extension

## Installation

```bash
composer require renzojohnson/wc-hpos-helper
```

## Usage

```php
use RenzoJohnson\WcHposHelper\HposHelper;

$helper = new HposHelper(
    dsn: 'mysql:host=localhost;dbname=wordpress',
    user: 'root',
    pass: 'secret',
);

// Full audit
$report = $helper->audit();
echo $report->toJson();

// Individual audits
$orderResult = $helper->auditOrders();
$subResult = $helper->auditSubscriptions();
$customerResult = $helper->auditCustomers();
$metaResult = $helper->auditMeta();

// Check HPOS status
$helper->isHposEnabled();  // bool
$helper->isSyncEnabled();  // bool
```

### Custom Table Prefix

```php
// Standard WordPress
$helper = new HposHelper($dsn, $user, $pass, prefix: 'wp_');

// Multisite (site 2)
$helper = new HposHelper($dsn, $user, $pass, prefix: 'wp_2_');

// Custom prefix
$helper = new HposHelper($dsn, $user, $pass, prefix: 'mysite_');
```

### Sample Limit

```php
$helper = new HposHelper($dsn, $user, $pass);
$helper->setSampleLimit(100); // Default is 50
```

## Audits

### Order Audit

Compares `wp_wc_orders` against `wp_posts` for mismatches in parent ID, customer ID, status, and totals. Only checks `shop_order` type. Status is normalized (lowercase, `wc-` prefix stripped). Totals use decimal-safe comparison.

### Subscription Audit

Checks subscriptions for `customer_id = 0` on active subscriptions (error) and `parent_order_id = 0` on cancelled subscriptions (warning). Detects orphaned renewal orders pointing to non-existent subscriptions.

### Customer Audit

Verifies `wp_wc_customer_lookup` matches order billing data. Checks email consistency and flags guest lookup rows and missing emails as warnings.

### Meta Sync Audit

Compares 12 canonical meta keys between HPOS and legacy tables. HPOS is treated as authoritative. Reports value mismatches and missing keys in the posts table.

## Report Format

```json
{
    "generated_at": "2026-02-24T15:00:00+00:00",
    "prefix": "wp_",
    "hpos_enabled": true,
    "sync_enabled": true,
    "summary": {
        "total_checked": 350,
        "total_mismatches": 5,
        "total_warnings": 3,
        "audits_passed": 2,
        "audits_failed": 1,
        "audits_skipped": 1,
        "overall_passed": false
    },
    "results": [...]
}
```

## Links

- [Packagist](https://packagist.org/packages/renzojohnson/wc-hpos-helper)
- [GitHub](https://github.com/renzojohnson/wc-hpos-helper)
- [Issues](https://github.com/renzojohnson/wc-hpos-helper/issues)
- [Author](https://renzojohnson.com)

## License

MIT License. Copyright (c) 2026 [Renzo Johnson](https://renzojohnson.com).
