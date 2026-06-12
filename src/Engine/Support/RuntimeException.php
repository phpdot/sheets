<?php

declare(strict_types=1);

namespace PHPdot\Sheets\Engine\Support;

/**
 * General runtime failure within phpdot/sheets.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */
final class RuntimeException extends \RuntimeException implements SheetsException {}
