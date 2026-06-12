<?php

declare(strict_types=1);

namespace PHPdot\Sheets\Engine\Xlsx;

use PHPdot\Sheets\Engine\Feature\DrawingCollector;
use PHPdot\Sheets\Engine\Feature\DrawingObject;
use PHPdot\Sheets\Engine\Feature\SheetTrailerOrder;
use PHPdot\Sheets\Engine\Feature\TrailerSink;

/**
 * Aggregates one worksheet's images and charts into a single
 * `xl/drawings/drawingN.xml` part (DrawingML `wsDr`), wires the drawing↔media,
 * drawing↔chart and worksheet↔drawing relationships, and contributes the
 * `<drawing>` worksheet trailer.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */
final class XlsxDrawingCollector implements DrawingCollector
{
    private const IMAGE_REL = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/image';
    private const CHART_REL = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/chart';
    private const DRAWING_REL = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/drawing';
    private const DRAWING_CONTENT_TYPE = 'application/vnd.openxmlformats-officedocument.drawing+xml';
    private const CHART_CONTENT_TYPE = 'application/vnd.openxmlformats-officedocument.drawingml.chart+xml';

    /** @var array<string, string> */
    private const MEDIA_CONTENT_TYPES = [
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'gif' => 'image/gif',
    ];

    private ?string $drawingPath = null;
    private int $nextObjectId = 0;

    /** @var list<string> */
    private array $anchors = [];

    public function __construct(
        private readonly PackageBuilder $package,
        private readonly string $sheetPartPath,
        private readonly PartCounter $drawingCounter,
        private readonly PartCounter $mediaCounter,
        private readonly PartCounter $chartCounter,
    ) {}

    public function embedImage(string $bytes, string $extension): DrawingObject
    {
        $extension = strtolower($extension);
        $drawingPath = $this->ensureDrawingPath();

        $name = sprintf('image%d.%s', $this->mediaCounter->next(), $extension);
        $this->package->addMediaPart('xl/media/' . $name, $bytes);
        $this->package->registerContentType($extension, self::MEDIA_CONTENT_TYPES[$extension] ?? 'application/octet-stream');

        $rid = $this->package->addRelationship($drawingPath, self::IMAGE_REL, '../media/' . $name);

        return new DrawingObject($rid, ++$this->nextObjectId);
    }

    public function embedChart(string $chartXml): DrawingObject
    {
        $drawingPath = $this->ensureDrawingPath();

        $name = sprintf('chart%d.xml', $this->chartCounter->next());
        $this->package->addXmlPart('xl/charts/' . $name, $chartXml);
        $this->package->registerContentType('/xl/charts/' . $name, self::CHART_CONTENT_TYPE);

        $rid = $this->package->addRelationship($drawingPath, self::CHART_REL, '../charts/' . $name);

        return new DrawingObject($rid, ++$this->nextObjectId);
    }

    public function addAnchor(string $markup): void
    {
        $this->anchors[] = $markup;
    }

    /**
     * Write the drawing part and contribute the worksheet `<drawing>` trailer.
     * No-op when nothing was placed on the sheet.
     */
    public function flush(TrailerSink $trailers): void
    {
        if ($this->anchors === [] || $this->drawingPath === null) {
            return;
        }

        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<xdr:wsDr xmlns:xdr="http://schemas.openxmlformats.org/drawingml/2006/spreadsheetDrawing"'
            . ' xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main"'
            . ' xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">';
        foreach ($this->anchors as $anchor) {
            $xml .= $anchor;
        }
        $xml .= '</xdr:wsDr>';

        $this->package->addXmlPart($this->drawingPath, $xml);
        $this->package->registerContentType('/' . $this->drawingPath, self::DRAWING_CONTENT_TYPE);

        $rid = $this->package->addRelationship(
            $this->sheetPartPath,
            self::DRAWING_REL,
            '../drawings/' . basename($this->drawingPath),
        );

        $trailers->add(SheetTrailerOrder::DRAWING, '<drawing r:id="' . $rid . '"/>');
    }

    private function ensureDrawingPath(): string
    {
        if ($this->drawingPath === null) {
            $this->drawingPath = sprintf('xl/drawings/drawing%d.xml', $this->drawingCounter->next());
        }

        return $this->drawingPath;
    }
}
