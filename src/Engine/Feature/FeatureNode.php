<?php

declare(strict_types=1);

namespace PHPdot\Sheets\Engine\Feature;

/**
 * A format-neutral description of one feature instance (a chart, an image, a
 * conditional-formatting rule, …). It describes the "what"; a {@see FeatureSerializer}
 * for the active codec translates it to the "how".
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */
interface FeatureNode
{
    /**
     * The capability this node belongs to, used to route it to a serializer.
     */
    public function capability(): Capability;
}
