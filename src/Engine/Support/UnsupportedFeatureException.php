<?php

declare(strict_types=1);

/**
 * Thrown (or logged, under capability/skip) when a codec has no serializer for a
 * requested feature capability.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Sheets\Engine\Support;

final class UnsupportedFeatureException extends \RuntimeException implements SheetsException {}
