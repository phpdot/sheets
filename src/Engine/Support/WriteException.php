<?php

declare(strict_types=1);

namespace PHPdot\Sheets\Engine\Support;

/**
 * Thrown when a write or packaging operation fails.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */
final class WriteException extends \RuntimeException implements SheetsException {}
