<?php

declare(strict_types=1);

/**
 * Page orientation for printing.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Sheets\Engine\Model;

enum Orientation: string
{
    case Portrait = 'portrait';
    case Landscape = 'landscape';
}
