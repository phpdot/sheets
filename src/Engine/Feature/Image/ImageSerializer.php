<?php

declare(strict_types=1);

/**
 * Renders an {@see ImageNode} as a DrawingML `oneCellAnchor` and embeds the media,
 * contributing to the sheet's shared drawing via the core {@see FeatureContext}.
 * Depends only on core — never on the XLSX codec.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Sheets\Engine\Feature\Image;

use PHPdot\Sheets\Engine\Feature\Capability;
use PHPdot\Sheets\Engine\Feature\FeatureContext;
use PHPdot\Sheets\Engine\Feature\FeatureNode;
use PHPdot\Sheets\Engine\Feature\FeatureSerializer;

final class ImageSerializer implements FeatureSerializer
{
    private const EMU_PER_PIXEL = 9525;

    public function capability(): Capability
    {
        return Capability::Images;
    }

    public function serialize(FeatureNode $node, FeatureContext $context): void
    {
        if (!$node instanceof ImageNode) {
            return;
        }

        $embedded = $context->drawing->embedImage($node->bytes, $node->extension);
        $cx = $node->widthPx * self::EMU_PER_PIXEL;
        $cy = $node->heightPx * self::EMU_PER_PIXEL;

        $context->drawing->addAnchor(
            '<xdr:oneCellAnchor>'
            . '<xdr:from>'
            . '<xdr:col>' . $node->column . '</xdr:col><xdr:colOff>0</xdr:colOff>'
            . '<xdr:row>' . $node->row . '</xdr:row><xdr:rowOff>0</xdr:rowOff>'
            . '</xdr:from>'
            . '<xdr:ext cx="' . $cx . '" cy="' . $cy . '"/>'
            . '<xdr:pic>'
            . '<xdr:nvPicPr>'
            . '<xdr:cNvPr id="' . $embedded->id . '" name="Image' . $embedded->id . '"/>'
            . '<xdr:cNvPicPr/>'
            . '</xdr:nvPicPr>'
            . '<xdr:blipFill>'
            . '<a:blip r:embed="' . $embedded->relationshipId . '"/>'
            . '<a:stretch><a:fillRect/></a:stretch>'
            . '</xdr:blipFill>'
            . '<xdr:spPr>'
            . '<a:prstGeom prst="rect"><a:avLst/></a:prstGeom>'
            . '</xdr:spPr>'
            . '</xdr:pic>'
            . '<xdr:clientData/>'
            . '</xdr:oneCellAnchor>',
        );
    }
}
