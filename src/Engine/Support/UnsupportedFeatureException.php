<?php

declare(strict_types=1);

namespace PHPdot\Sheets\Engine\Support;

/**
 * Thrown (or logged, under capability/skip) when a codec has no serializer for a
 * requested feature capability.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */
final class UnsupportedFeatureException extends \RuntimeException implements SheetsException {}
