<?php

declare(strict_types=1);

namespace PHPdot\Sheets\Engine\Feature\Chart;

use PHPdot\Sheets\Engine\Feature\Capability;
use PHPdot\Sheets\Engine\Feature\FeatureContext;
use PHPdot\Sheets\Engine\Feature\FeatureNode;
use PHPdot\Sheets\Engine\Feature\FeatureSerializer;

/**
 * Renders a {@see ChartNode} as a chart part plus a DrawingML `graphicFrame`
 * anchor, contributing to the sheet's shared drawing via the core
 * {@see FeatureContext}. Depends only on core — never on the XLSX codec.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */
final class ChartSerializer implements FeatureSerializer
{
    private const EMU_PER_PIXEL = 9525;
    private const NS_CHART = 'http://schemas.openxmlformats.org/drawingml/2006/chart';

    public function capability(): Capability
    {
        return Capability::Charts;
    }

    public function serialize(FeatureNode $node, FeatureContext $context): void
    {
        if (!$node instanceof ChartNode) {
            return;
        }

        $chart = $context->drawing->embedChart((new ChartXmlBuilder())->build($node));
        $cx = $node->widthPx * self::EMU_PER_PIXEL;
        $cy = $node->heightPx * self::EMU_PER_PIXEL;

        $context->drawing->addAnchor(
            '<xdr:oneCellAnchor>'
            . '<xdr:from>'
            . '<xdr:col>' . $node->column . '</xdr:col><xdr:colOff>0</xdr:colOff>'
            . '<xdr:row>' . $node->row . '</xdr:row><xdr:rowOff>0</xdr:rowOff>'
            . '</xdr:from>'
            . '<xdr:ext cx="' . $cx . '" cy="' . $cy . '"/>'
            . '<xdr:graphicFrame macro="">'
            . '<xdr:nvGraphicFramePr>'
            . '<xdr:cNvPr id="' . $chart->id . '" name="Chart' . $chart->id . '"/>'
            . '<xdr:cNvGraphicFramePr/>'
            . '</xdr:nvGraphicFramePr>'
            . '<xdr:xfrm><a:off x="0" y="0"/><a:ext cx="0" cy="0"/></xdr:xfrm>'
            . '<a:graphic>'
            . '<a:graphicData uri="' . self::NS_CHART . '">'
            . '<c:chart xmlns:c="' . self::NS_CHART . '" r:id="' . $chart->relationshipId . '"/>'
            . '</a:graphicData>'
            . '</a:graphic>'
            . '</xdr:graphicFrame>'
            . '<xdr:clientData/>'
            . '</xdr:oneCellAnchor>',
        );
    }
}
