<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Demodata\Generator;

use Shopware\Core\Content\Product\Aggregate\ProductManufacturer\ProductManufacturerDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriterInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopware\Core\Framework\Demodata\DemodataContext;
use Shopware\Core\Framework\Demodata\DemodataGeneratorInterface;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @deprecated tag:v6.5.0 - reason:becomes-internal - will be internal in 6.5.0
 *
 * @package inventory
 */
class ProductManufacturerGenerator implements DemodataGeneratorInterface
{
    private EntityWriterInterface $writer;

    private ProductManufacturerDefinition $productManufacturerDefinition;

    /**
     * @internal
     */
    public function __construct(
        EntityWriterInterface $writer,
        ProductManufacturerDefinition $productManufacturerDefinition
    ) {
        $this->writer = $writer;
        $this->productManufacturerDefinition = $productManufacturerDefinition;
    }

    public function getDefinition(): string
    {
        return ProductManufacturerDefinition::class;
    }

    public function generate(int $numberOfItems, DemodataContext $context, array $options = []): void
    {
        $context->getConsole()->progressStart($numberOfItems);

        $payload = [];
        for ($i = 0; $i < $numberOfItems; ++$i) {
            $payload[] = [
                'id' => Uuid::randomHex(),
                'name' => $context->getFaker()->format('company'),
                'link' => $context->getFaker()->format('url'),
            ];
        }

        $writeContext = WriteContext::createFromContext($context->getContext());

        foreach (array_chunk($payload, 100) as $chunk) {
            $this->writer->upsert($this->productManufacturerDefinition, $chunk, $writeContext);
            $context->getConsole()->progressAdvance(\count($chunk));
        }

        $context->getConsole()->progressFinish();
    }
}
