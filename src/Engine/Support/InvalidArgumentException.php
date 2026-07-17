<?php

declare(strict_types=1);

/**
 * Thrown when an argument is outside its permitted domain.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Sheets\Engine\Support;

final class InvalidArgumentException extends \InvalidArgumentException implements SheetsException {}
