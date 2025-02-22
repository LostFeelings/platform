<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Api\Sync;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Test\Product\ProductBuilder;
use Shopware\Core\Framework\Api\Sync\SyncBehavior;
use Shopware\Core\Framework\Api\Sync\SyncOperation;
use Shopware\Core\Framework\Api\Sync\SyncService;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\CashRoundingConfig;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteException;
use Shopware\Core\Framework\Test\IdsCollection;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseHelper\CallableClass;
use Shopware\Core\Framework\Test\TestDataCollection;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\WriteConstraintViolationException;

/**
 * @internal
 */
class SyncServiceTest extends TestCase
{
    use IntegrationTestBehaviour;

    private SyncService $service;

    private Connection $connection;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = $this->getContainer()->get(SyncService::class);
        $this->connection = $this->getContainer()->get(Connection::class);
    }

    public function testDeleteProductMediaAndUpdateProduct(): void
    {
        $ids = new IdsCollection();
        $product = (new ProductBuilder($ids, 'p1'))
            ->price(100)
            ->media('media-1')
            ->media('media-2')
            ->media('media-3')
            ->build();

        $this->getContainer()->get('product.repository')
            ->create([$product], Context::createDefaultContext());

        $operations = [
            new SyncOperation('delete-media', 'product_media', 'delete', [['id' => $ids->get('media-2')]]),
            new SyncOperation('update-product', 'product', 'upsert', [['id' => $ids->get('p1'), 'media' => [['id' => $ids->get('media-3'), 'position' => 10]]]]),
        ];

        $this->service->sync($operations, Context::createDefaultContext(), new SyncBehavior());
    }

    public function testSingleOperationWithDeletesAndWrites(): void
    {
        $ids = new TestDataCollection();

        $currency = [
            'name' => 'test',
            'factor' => 2,
            'symbol' => '€',
            'itemRounding' => json_decode(json_encode(new CashRoundingConfig(2, 0.01, true), \JSON_THROW_ON_ERROR), true),
            'totalRounding' => json_decode(json_encode(new CashRoundingConfig(2, 0.01, true), \JSON_THROW_ON_ERROR), true),
            'shortName' => 'TEST',
        ];

        $this->getContainer()->get('currency.repository')->create(
            [
                array_merge($currency, ['id' => $ids->get('currency-1'), 'isoCode' => 'xx']),
                array_merge($currency, ['id' => $ids->get('currency-2'), 'isoCode' => 'xy']),
            ],
            Context::createDefaultContext()
        );

        $product = (new ProductBuilder($ids, 'test', 1, 'tax-1'))->price(100);

        $operations = [
            new SyncOperation('write', 'product_manufacturer', SyncOperation::ACTION_UPSERT, [
                ['id' => $ids->create('m1'), 'name' => 'first manufacturer'],
                ['id' => $ids->create('m2'), 'name' => 'second manufacturer'],
            ]),
            new SyncOperation('write-tax', 'tax', SyncOperation::ACTION_UPSERT, [
                ['id' => $ids->create('t1'), 'name' => 'first tax', 'taxRate' => 10],
                ['id' => $ids->create('t2'), 'name' => 'second tax', 'taxRate' => 10],
            ]),
            new SyncOperation('write', 'country', SyncOperation::ACTION_UPSERT, [
                ['id' => $ids->create('c1'), 'name' => 'first country'],
                ['id' => $ids->create('c2'), 'name' => 'second country'],
            ]),
            new SyncOperation('multi-pk', 'product', SyncOperation::ACTION_UPSERT, [
                $product->build(),
            ]),
            new SyncOperation('not-found', 'product', SyncOperation::ACTION_DELETE, [
                ['id' => $ids->get('p1')],
                ['id' => $ids->get('p2')],
                ['id' => $ids->get('p3')],
            ]),
            new SyncOperation('delete-currencies', 'currency', SyncOperation::ACTION_DELETE, [
                ['id' => $ids->get('currency-1')],
                ['id' => $ids->get('currency-2')],
            ]),
        ];

        $this->service->sync($operations, Context::createDefaultContext(), new SyncBehavior());

        $dispatcher = $this->getContainer()->get('event_dispatcher');

        $listener = $this
            ->getMockBuilder(CallableClass::class)
            ->getMock();

        $listener->expects(static::once())
            ->method('__invoke');

        $this->addEventListener($dispatcher, EntityWrittenContainerEvent::class, $listener);

        $operations = [
            new SyncOperation('manufacturers', 'product_manufacturer', SyncOperation::ACTION_UPSERT, [
                ['id' => $ids->create('m3'), 'name' => 'third manufacturer'],
                ['id' => $ids->create('m4'), 'name' => 'fourth manufacturer'],
            ]),
            new SyncOperation('taxes', 'tax', SyncOperation::ACTION_DELETE, [
                ['id' => $ids->get('t1')],
                ['id' => $ids->get('t2')],
            ]),
            new SyncOperation('countries', 'country', SyncOperation::ACTION_DELETE, [
                ['id' => $ids->get('c1')],
                ['id' => $ids->get('c2')],
            ]),
        ];

        $this->service->sync($operations, Context::createDefaultContext(), new SyncBehavior());

        $exists = $this->connection->fetchAllAssociative(
            'SELECT id FROM product_manufacturer WHERE id IN (:ids)',
            ['ids' => Uuid::fromHexToBytesList($ids->getList(['m1', 'm2', 'm3', 'm4']))],
            ['ids' => Connection::PARAM_STR_ARRAY]
        );
        static::assertCount(4, $exists);

        $exists = $this->connection->fetchAllAssociative(
            'SELECT id FROM tax WHERE id IN (:ids)',
            ['ids' => Uuid::fromHexToBytesList($ids->getList(['t1', 't2']))],
            ['ids' => Connection::PARAM_STR_ARRAY]
        );
        static::assertEmpty($exists);

        $exists = $this->connection->fetchAllAssociative(
            'SELECT id FROM country WHERE id IN (:ids)',
            ['ids' => Uuid::fromHexToBytesList($ids->getList(['c1', 'c2']))],
            ['ids' => Connection::PARAM_STR_ARRAY]
        );
        static::assertEmpty($exists);
    }

    public function testSingleOperationParameter(): void
    {
        $ids = new TestDataCollection();

        $dispatcher = $this->getContainer()->get('event_dispatcher');

        $listener = $this
            ->getMockBuilder(CallableClass::class)
            ->getMock();

        $listener->expects(static::once())
            ->method('__invoke');

        $this->addEventListener($dispatcher, EntityWrittenContainerEvent::class, $listener);

        $operations = [
            new SyncOperation('write', 'product_manufacturer', SyncOperation::ACTION_UPSERT, [
                ['id' => $ids->create('m1'), 'name' => 'first manufacturer'],
                ['id' => $ids->create('m2'), 'name' => 'second manufacturer'],
            ]),
            new SyncOperation('write', 'tax', SyncOperation::ACTION_UPSERT, [
                ['id' => $ids->create('t1'), 'name' => 'first tax', 'taxRate' => 10],
                ['id' => $ids->create('t2'), 'name' => 'second tax', 'taxRate' => 10],
            ]),
            new SyncOperation('write', 'country', SyncOperation::ACTION_UPSERT, [
                ['id' => $ids->create('c1'), 'name' => 'first country'],
                ['id' => $ids->create('c2'), 'name' => 'second country'],
            ]),
        ];

        $this->service->sync($operations, Context::createDefaultContext(), new SyncBehavior());
    }

    public function testError(): void
    {
        $id1 = Uuid::randomHex();
        $id2 = Uuid::randomHex();

        $operation = new SyncOperation(
            'manufacturers',
            'product_manufacturer',
            SyncOperation::ACTION_UPSERT,
            [
                ['id' => $id1, 'name' => 'first manufacturer'],
                ['id' => $id2],
                ['id' => Uuid::randomHex()],
                ['id' => Uuid::randomHex()],
                ['id' => Uuid::randomHex()],
            ]
        );

        $e = null;

        try {
            $this->service->sync([$operation], Context::createDefaultContext(), new SyncBehavior());
        } catch (WriteException $e) {
        }

        static::assertInstanceOf(WriteException::class, $e);

        static::assertCount(4, $e->getExceptions());
        $first = $e->getExceptions()[0];

        /** @var WriteConstraintViolationException $first */
        static::assertInstanceOf(WriteConstraintViolationException::class, $first);
        static::assertStringStartsWith('/manufacturers/1/translations', $first->getPath());
    }
}
