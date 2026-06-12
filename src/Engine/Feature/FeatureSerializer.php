<?php

declare(strict_types=1);

namespace PHPdot\Sheets\Engine\Feature;

/**
 * Translates a format-neutral {@see FeatureNode} into XLSX package parts. Lives
 * in the feature package, depending only on the neutral model.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */
interface FeatureSerializer
{
    /**
     * The capability this serializer handles.
     */
    public function capability(): Capability;

    /**
     * Emit the node's package parts via {@see FeatureContext::$package}.
     */
    public function serialize(FeatureNode $node, FeatureContext $context): void;
}
