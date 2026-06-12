<?php

declare(strict_types=1);

namespace PHPdot\Sheets\Engine\Feature;

/**
 * The user-facing handle for a feature, passed to a writer's `use()`. It exposes
 * the per-format serializers the feature ships (e.g. an XLSX serializer).
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */
interface FeaturePlugin
{
    /**
     * The serializers this feature provides, one per supported format.
     *
     * @return list<FeatureSerializer>
     */
    public function serializers(): array;
}
