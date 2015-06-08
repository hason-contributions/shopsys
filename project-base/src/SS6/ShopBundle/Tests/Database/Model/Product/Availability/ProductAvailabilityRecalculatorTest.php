<?php

namespace SS6\ShopBundle\Tests\Database\Model\Product\Availability;

use SS6\ShopBundle\DataFixtures\Base\AvailabilityDataFixture;
use SS6\ShopBundle\Model\Product\Product;
use SS6\ShopBundle\Tests\Test\DatabaseTestCase;

class ProductAvailabilityRecalculatorTest extends DatabaseTestCase {

	public function testRecalculateOnProductEditNotUsingStock() {
		$productEditFacade = $this->getContainer()->get('ss6.shop.product.product_edit_facade');
		/* @var $productEditFacade \SS6\ShopBundle\Model\Product\ProductEditFacade */
		$productEditDataFactory = $this->getContainer()->get('ss6.shop.product.product_edit_data_factory');
		/* @var $productEditDataFactory \SS6\ShopBundle\Model\Product\ProductEditDataFactory */
		$productAvailabilityRecalculator = $this->getContainer()
			->get('ss6.shop.product.availability.product_availability_recalculator');
		/* @var $productAvailabilityRecalculator \SS6\ShopBundle\Model\Product\Availability\ProductAvailabilityRecalculator */

		$productId = 1;

		$product = $productEditFacade->getById($productId);

		$productEditData = $productEditDataFactory->createFromProduct($product);
		$productEditData->productData->usingStock = false;
		$productEditData->productData->availability = $this->getReference(AvailabilityDataFixture::ON_REQUEST);
		$productEditData->urls['toDelete'] = [];

		$productEditFacade->edit($productId, $productEditData);
		$productAvailabilityRecalculator->runScheduledRecalculations(function () {
			return true;
		});
		$this->getEntityManager()->flush();
		$this->getEntityManager()->clear();

		$productFromDb = $productEditFacade->getById($productId);

		$this->assertSame($this->getReference(AvailabilityDataFixture::ON_REQUEST), $productFromDb->getCalculatedAvailability());
	}

	public function testRecalculateOnProductEditUsingStockInStock() {
		$productEditFacade = $this->getContainer()->get('ss6.shop.product.product_edit_facade');
		/* @var $productEditFacade \SS6\ShopBundle\Model\Product\ProductEditFacade */
		$productEditDataFactory = $this->getContainer()->get('ss6.shop.product.product_edit_data_factory');
		/* @var $productEditDataFactory \SS6\ShopBundle\Model\Product\ProductEditDataFactory */
		$availabilityFacade = $this->getContainer()->get('ss6.shop.product.availability.availability_facade');
		/* @var $availabilityFacade \SS6\ShopBundle\Model\Product\Availability\AvailabilityFacade */
		$productAvailabilityRecalculator = $this->getContainer()
			->get('ss6.shop.product.availability.product_availability_recalculator');
		/* @var $productAvailabilityRecalculator \SS6\ShopBundle\Model\Product\Availability\ProductAvailabilityRecalculator */

		$productId = 1;

		$product = $productEditFacade->getById($productId);

		$productEditData = $productEditDataFactory->createFromProduct($product);
		$productEditData->productData->usingStock = true;
		$productEditData->productData->stockQuantity = 5;
		$productEditData->productData->outOfStockAvailability = $this->getReference(AvailabilityDataFixture::OUT_OF_STOCK);
		$productEditData->productData->availability = null;
		$productEditData->urls['toDelete'] = [];

		$productEditFacade->edit($productId, $productEditData);
		$productAvailabilityRecalculator->runScheduledRecalculations(function () {
			return true;
		});
		$this->getEntityManager()->flush();
		$this->getEntityManager()->clear();

		$productFromDb = $productEditFacade->getById($productId);

		$this->assertSame($availabilityFacade->getDefaultInStockAvailability(), $productFromDb->getCalculatedAvailability());
	}

	public function testRecalculateOnProductEditUsingStockOutOfStock() {
		$productEditFacade = $this->getContainer()->get('ss6.shop.product.product_edit_facade');
		/* @var $productEditFacade \SS6\ShopBundle\Model\Product\ProductEditFacade */
		$productEditDataFactory = $this->getContainer()->get('ss6.shop.product.product_edit_data_factory');
		/* @var $productEditDataFactory \SS6\ShopBundle\Model\Product\ProductEditDataFactory */
		$productAvailabilityRecalculator = $this->getContainer()
			->get('ss6.shop.product.availability.product_availability_recalculator');
		/* @var $productAvailabilityRecalculator \SS6\ShopBundle\Model\Product\Availability\ProductAvailabilityRecalculator */

		$productId = 1;

		$product = $productEditFacade->getById($productId);

		$productEditData = $productEditDataFactory->createFromProduct($product);
		$productEditData->productData->usingStock = true;
		$productEditData->productData->stockQuantity = 0;
		$productEditData->productData->outOfStockAction = Product::OUT_OF_STOCK_ACTION_SET_ALTERNATE_AVAILABILITY;
		$productEditData->productData->outOfStockAvailability = $this->getReference(AvailabilityDataFixture::OUT_OF_STOCK);
		$productEditData->productData->availability = null;
		$productEditData->urls['toDelete'] = [];

		$productEditFacade->edit($productId, $productEditData);
		$productAvailabilityRecalculator->runScheduledRecalculations(function () {
			return true;
		});
		$this->getEntityManager()->flush();
		$this->getEntityManager()->clear();

		$productFromDb = $productEditFacade->getById($productId);

		$this->assertSame($this->getReference(AvailabilityDataFixture::OUT_OF_STOCK), $productFromDb->getCalculatedAvailability());
	}

}