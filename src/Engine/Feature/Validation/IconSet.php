<?php

declare(strict_types=1);

namespace PHPdot\Sheets\Engine\Feature\Validation;

/**
 * A conditional-formatting icon set — the complete ECMA-376 `ST_IconSetType` set.
 * The value is the OOXML token; its leading digit is the number of icons (and
 * evenly-spaced percentage thresholds).
 *
 * (The Excel-2010 sets 3Stars/3Triangles/5Boxes use a separate `x14` extension
 * markup and are out of scope here.)
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */
enum IconSet: string
{
    // Three icons
    case ThreeArrows = '3Arrows';
    case ThreeArrowsGray = '3ArrowsGray';
    case ThreeFlags = '3Flags';
    case ThreeTrafficLights = '3TrafficLights1';
    case ThreeTrafficLightsRimmed = '3TrafficLights2';
    case ThreeSigns = '3Signs';
    case ThreeSymbols = '3Symbols';
    case ThreeSymbolsUncircled = '3Symbols2';

    // Four icons
    case FourArrows = '4Arrows';
    case FourArrowsGray = '4ArrowsGray';
    case FourRedToBlack = '4RedToBlack';
    case FourRating = '4Rating';
    case FourTrafficLights = '4TrafficLights';

    // Five icons
    case FiveArrows = '5Arrows';
    case FiveArrowsGray = '5ArrowsGray';
    case FiveQuarters = '5Quarters';
    case FiveRating = '5Rating';
}
