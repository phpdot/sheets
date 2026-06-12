<?php

declare(strict_types=1);

namespace PHPdot\Sheets\Engine\Support;

/**
 * Thrown when an argument is outside its permitted domain.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */
final class InvalidArgumentException extends \InvalidArgumentException implements SheetsException {}
