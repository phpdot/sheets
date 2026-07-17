<?php

declare(strict_types=1);

/**
 * General runtime failure within phpdot/sheets.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Sheets\Engine\Support;

final class RuntimeException extends \RuntimeException implements SheetsException {}
