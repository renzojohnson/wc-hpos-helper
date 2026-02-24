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

namespace RenzoJohnson\WcHposHelper\Audit;

/**
 * Contract for all HPOS audit runners.
 *
 * Implementations receive DatabaseInterface and TableResolver via constructor.
 */
interface AuditInterface
{
    public const int MAX_SAMPLES = 50;

    public function run(): AuditResult;
}
