<?php

declare(strict_types=1);

/**
 * Thrown when a write or packaging operation fails.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Sheets\Engine\Support;

final class WriteException extends \RuntimeException implements SheetsException {}
