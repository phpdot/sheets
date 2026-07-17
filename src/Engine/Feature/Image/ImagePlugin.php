<?php

declare(strict_types=1);

/**
 * The image feature: pass to a writer's `use()` to enable embedding images.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Sheets\Engine\Feature\Image;

use PHPdot\Sheets\Engine\Feature\FeaturePlugin;

final class ImagePlugin implements FeaturePlugin
{
    public function serializers(): array
    {
        return [new ImageSerializer()];
    }
}
