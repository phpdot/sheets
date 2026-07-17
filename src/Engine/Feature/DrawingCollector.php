<?php

declare(strict_types=1);

/**
 * Per-sheet drawing aggregator. A worksheet has exactly one drawing part holding
 * many anchors, shared by every image and chart on that sheet — so image/chart
 * serializers embed their media/chart part here and contribute one anchor each,
 * and the codec assembles the single drawing part and links it to the worksheet.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Sheets\Engine\Feature;

interface DrawingCollector
{
    /**
     * Embed image bytes and return a handle (embed relationship id + object id)
     * for the caller to reference in its picture anchor.
     *
     * @param string $bytes
     * @param string $extension
     *
     * @return DrawingObject
     */
    public function embedImage(string $bytes, string $extension): DrawingObject;

    /**
     * Add a chart part and return a handle (relationship id + object id) for the
     * caller to reference in its graphic-frame anchor.
     *
     * @param string $chartXml
     *
     * @return DrawingObject
     */
    public function embedChart(string $chartXml): DrawingObject;

    /**
     * Contribute one format-specific anchor fragment to this sheet's drawing.
     *
     * @param string $markup
     *
     * @return void
     */
    public function addAnchor(string $markup): void;
}
