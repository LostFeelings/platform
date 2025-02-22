<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\DataAbstractionLayer\StockUpdate;

use Shopware\Core\Framework\Context;

abstract class AbstractStockUpdateFilter
{
    /**
     * @param list<string> $ids
     *
     * @return list<string>
     */
    abstract public function filter(array $ids, Context $context): array;
}
