<?php

declare(strict_types=1);

/**
 * Translates a format-neutral {@see FeatureNode} into XLSX package parts. Lives
 * in the feature package, depending only on the neutral model.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Sheets\Engine\Feature;

interface FeatureSerializer
{
    /**
     * The capability this serializer handles.
     *
     * @return Capability
     */
    public function capability(): Capability;

    /**
     * Emit the node's package parts via {@see FeatureContext::$package}.
     *
     * @param FeatureNode $node
     * @param FeatureContext $context
     *
     * @return void
     */
    public function serialize(FeatureNode $node, FeatureContext $context): void;
}
