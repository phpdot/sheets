<?php

declare(strict_types=1);

namespace PHPdot\Sheets\Engine\Feature\Validation;

use PHPdot\Sheets\Engine\Feature\Capability;
use PHPdot\Sheets\Engine\Feature\FeatureContext;
use PHPdot\Sheets\Engine\Feature\FeatureNode;
use PHPdot\Sheets\Engine\Feature\FeatureSerializer;
use PHPdot\Sheets\Engine\Feature\SheetTrailerOrder;
use PHPdot\Sheets\Engine\Support\Xml;

/**
 * Renders a {@see DataValidationNode} (list / numeric / date / text-length /
 * custom, with optional input & error messages) as a `<dataValidation>`,
 * grouped under one `<dataValidations>` container per sheet.
 * Depends only on core — never on the XLSX codec.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */
final class DataValidationSerializer implements FeatureSerializer
{
    public function capability(): Capability
    {
        return Capability::DataValidation;
    }

    public function serialize(FeatureNode $node, FeatureContext $context): void
    {
        if (!$node instanceof DataValidationNode) {
            return;
        }

        $xml = '<dataValidation type="' . $node->type->value . '"';
        if ($node->operator !== null
            && $node->type !== ValidationType::List
            && $node->type !== ValidationType::Custom
        ) {
            $xml .= ' operator="' . $node->operator->value . '"';
        }
        $xml .= ' allowBlank="' . ($node->allowBlank ? '1' : '0') . '"';

        if ($node->promptTitle !== null || $node->prompt !== null) {
            $xml .= ' showInputMessage="1"';
            if ($node->promptTitle !== null) {
                $xml .= ' promptTitle="' . Xml::attribute($node->promptTitle) . '"';
            }
            if ($node->prompt !== null) {
                $xml .= ' prompt="' . Xml::attribute($node->prompt) . '"';
            }
        }

        // Enforce and show the error alert (Excel's default unless customized).
        $xml .= ' showErrorMessage="1"';
        if ($node->errorTitle !== null) {
            $xml .= ' errorTitle="' . Xml::attribute($node->errorTitle) . '"';
        }
        if ($node->error !== null) {
            $xml .= ' error="' . Xml::attribute($node->error) . '"';
        }

        $xml .= ' sqref="' . Xml::attribute($node->sqref) . '">';

        $formula1 = $this->formula1($node);
        if ($formula1 !== null) {
            $xml .= '<formula1>' . Xml::text($formula1) . '</formula1>';
        }
        if ($node->formula2 !== null) {
            $xml .= '<formula2>' . Xml::text($node->formula2) . '</formula2>';
        }
        $xml .= '</dataValidation>';

        $context->trailers->add(SheetTrailerOrder::DATA_VALIDATIONS, $xml, 'dataValidations');
    }

    private function formula1(DataValidationNode $node): ?string
    {
        if ($node->type === ValidationType::List && $node->values !== []) {
            // Quotes inside an Excel string literal are escaped by doubling.
            return '"' . str_replace('"', '""', implode(',', $node->values)) . '"';
        }

        return $node->formula1;
    }
}
