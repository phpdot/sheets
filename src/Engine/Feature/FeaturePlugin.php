<?php

declare(strict_types=1);

/**
 * The user-facing handle for a feature, passed to a writer's `use()`. It exposes
 * the per-format serializers the feature ships (e.g. an XLSX serializer).
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Sheets\Engine\Feature;

interface FeaturePlugin
{
    /**
     * The serializers this feature provides, one per supported format.
     *
     * @return list<FeatureSerializer>
     */
    public function serializers(): array;
}
