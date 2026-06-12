<?php

declare(strict_types=1);

namespace PHPdot\Sheets\Engine\Support;

/**
 * Thrown when a file cannot be read or is not a valid spreadsheet package.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */
final class ReadException extends \RuntimeException implements SheetsException {}
