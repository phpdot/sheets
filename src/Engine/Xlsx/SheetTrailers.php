<?php

declare(strict_types=1);

namespace PHPdot\Sheets\Engine\Xlsx;

use PHPdot\Sheets\Engine\Feature\TrailerSink;

/**
 * Collects worksheet trailing fragments and emits them in CT_Worksheet order,
 * regardless of the order features contributed them. PHP's `usort` is stable
 * (>= 8.0), so fragments with equal rank keep insertion order.
 *
 * Fragments tagged with a `$group` (e.g. "dataValidations") are wrapped together
 * in one `<group count="N">…</group>` container — required for singleton parent
 * elements that hold many children.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */
final class SheetTrailers implements TrailerSink
{
    /** @var list<array{order: int, xml: string, group: ?string}> */
    private array $items = [];

    public function add(int $order, string $xml, ?string $group = null): void
    {
        $this->items[] = ['order' => $order, 'xml' => $xml, 'group' => $group];
    }

    public function toXml(): string
    {
        usort($this->items, static fn(array $a, array $b): int => $a['order'] <=> $b['order']);

        $xml = '';
        $count = count($this->items);
        $i = 0;

        while ($i < $count) {
            $group = $this->items[$i]['group'];

            if ($group === null) {
                $xml .= $this->items[$i]['xml'];
                $i++;

                continue;
            }

            $children = '';
            $n = 0;
            while ($i < $count && $this->items[$i]['group'] === $group) {
                $children .= $this->items[$i]['xml'];
                $n++;
                $i++;
            }
            $xml .= '<' . $group . ' count="' . $n . '">' . $children . '</' . $group . '>';
        }

        return $xml;
    }
}
