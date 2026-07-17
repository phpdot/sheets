<?php

declare(strict_types=1);

/**
 * A feature a codec may or may not be able to serialize.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Sheets\Engine\Feature;

enum Capability: string
{
    case Styles = 'styles';
    case Charts = 'charts';
    case Images = 'images';
    case ConditionalFormatting = 'conditional_formatting';
    case DataValidation = 'data_validation';
}
