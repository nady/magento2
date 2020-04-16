<?php declare(strict_types=1);
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Magento\CatalogUrlRewrite\Test\Unit\Service\V1;

use Magento\CatalogUrlRewrite\Service\V1\StoreViewService;
use Magento\Eav\Model\Config;
use Magento\Eav\Model\Entity\AbstractEntity;
use Magento\Eav\Model\Entity\Attribute\AbstractAttribute;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select as DbSelect;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class StoreViewServiceTest extends TestCase
{
    /** @var StoreViewService */
    protected $storeViewService;

    /** @var Config|MockObject */
    protected $config;

    /** @var ResourceConnection|MockObject */
    protected $resource;

    /** @var AdapterInterface|MockObject */
    protected $connection;

    /** @var  DbSelect|MockObject */
    protected $select;

    protected function setUp():void
    {
        $this->config = $this->createMock(Config::class);
        $this->select = $this->getMockBuilder(DbSelect::class)
            ->setMethods(['select', 'from', 'where', 'join'])
            ->disableOriginalConstructor()
            ->getMock();
        $this->connection = $this->getMockBuilder(AdapterInterface::class)
            ->disableOriginalConstructor()
            ->getMockForAbstractClass();
        $this->resource = $this->createMock(ResourceConnection::class);
        $this->resource->expects($this->any())->method('getConnection')->willReturn($this->connection);

        $this->storeViewService = (new ObjectManager($this))->getObject(
            StoreViewService::class,
            [
                'eavConfig' => $this->config,
                'resource' => $this->resource,
            ]
        );
    }

    /**
     * @return array
     */
    public function isRootCategoryForStoreDataProvider()
    {
        return [
            [1, 1, 1, true],
            [1, 2, 1, false],
            [2, 0, 1, false],
        ];
    }

    /**
     * @return array
     */
    public function overriddenUrlKeyForStoreDataProvider()
    {
        return [
            [1, [1, 2], true],
            [1, [2, 3], false],
            [1, [], false],
        ];
    }

    /**
     * @dataProvider overriddenUrlKeyForStoreDataProvider
     * @param int $storeId
     * @param array $fetchedStoreIds
     * @param bool $result
     */
    public function testDoesEntityHaveOverriddenUrlKeyForStore($storeId, $fetchedStoreIds, $result)
    {
        $entityType = 'entity_type';
        $productId = 'product_id';
        $attribute = $this->getMockBuilder(AbstractAttribute::class)
            ->disableOriginalConstructor()
            ->setMethods(['__wakeup', 'getBackendTable', 'getId', 'getEntity'])
            ->getMockForAbstractClass();
        $this->config->expects($this->once())->method('getAttribute')->with($entityType, 'url_key')
            ->will($this->returnValue($attribute));
        $entity = $this->getMockBuilder(AbstractEntity::class)
            ->disableOriginalConstructor()
            ->getMock();
        $attribute->expects($this->exactly(2))->method('getEntity')->willReturn($entity);
        $entity->expects($this->once())->method('getEntityTable')->will($this->returnValue('entity_table'));
        $entity->expects($this->once())->method('getLinkField')->willReturn('link_field');
        $attribute->expects($this->once())->method('getBackendTable')->will($this->returnValue('backend_table'));
        $attribute->expects($this->once())->method('getId')->will($this->returnValue('attribute-id'));
        $this->select->expects($this->once())->method('from')->with(['e' => 'entity_table'], [])
            ->will($this->returnSelf());
        $this->select->expects($this->any())->method('where')->will($this->returnSelf());
        $this->select->expects($this->once())->method('join')->with(
            ['e_attr' => 'backend_table'],
            "e.link_field = e_attr.link_field",
            'store_id'
        )->will($this->returnSelf());
        $this->connection->expects($this->once())->method('select')->will($this->returnValue($this->select));
        $this->connection->expects($this->once())->method('fetchCol')->will($this->returnValue($fetchedStoreIds));

        $this->assertEquals(
            $result,
            $this->storeViewService->doesEntityHaveOverriddenUrlKeyForStore($storeId, $productId, $entityType)
        );
    }

    public function testInvalidAttributeRetrieve()
    {
        $this->expectException('InvalidArgumentException');
        $this->expectExceptionMessage('Cannot retrieve attribute for entity type "invalid_type"');
        $invalidEntityType = 'invalid_type';
        $this->config->expects($this->once())->method('getAttribute')->will($this->returnValue(false));

        $this->storeViewService->doesEntityHaveOverriddenUrlKeyForStore(1, 1, $invalidEntityType);
    }
}
