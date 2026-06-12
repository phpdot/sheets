<?php

declare(strict_types=1);

namespace PHPdot\Sheets\Engine\Feature;

/**
 * Per-sheet drawing aggregator. A worksheet has exactly one drawing part holding
 * many anchors, shared by every image and chart on that sheet — so image/chart
 * serializers embed their media/chart part here and contribute one anchor each,
 * and the codec assembles the single drawing part and links it to the worksheet.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */
interface DrawingCollector
{
    /**
     * Embed image bytes and return a handle (embed relationship id + object id)
     * for the caller to reference in its picture anchor.
     */
    public function embedImage(string $bytes, string $extension): DrawingObject;

    /**
     * Add a chart part and return a handle (relationship id + object id) for the
     * caller to reference in its graphic-frame anchor.
     */
    public function embedChart(string $chartXml): DrawingObject;

    /**
     * Contribute one format-specific anchor fragment to this sheet's drawing.
     */
    public function addAnchor(string $markup): void;
}
