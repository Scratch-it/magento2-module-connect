<?php
/**
 * Copyright (c) Zembula. Licensed under the MIT License; see LICENSE.
 */
declare(strict_types=1);

namespace Zembula\Connect\Plugin;

use Magento\Catalog\Model\ResourceModel\Product as ProductResource;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\Data\OrderItemExtensionFactory;
use Magento\Sales\Api\Data\OrderItemInterface;
use Magento\Sales\Api\Data\OrderSearchResultInterface;
use Magento\Sales\Api\OrderRepositoryInterface;

/**
 * Adds extension_attributes.zembula_parent_sku to configurable order lines.
 *
 * Both the configurable line and its child line get the CURRENT catalog SKU of the configurable
 * product the line was bought through. One SQL query per get() / getList() call.
 */
class OrderParentSku
{
    /**
     * @var ProductResource
     */
    private $productResource;

    /**
     * @var OrderItemExtensionFactory
     */
    private $extensionFactory;

    /**
     * @param ProductResource $productResource
     * @param OrderItemExtensionFactory $extensionFactory
     */
    public function __construct(ProductResource $productResource, OrderItemExtensionFactory $extensionFactory)
    {
        $this->productResource = $productResource;
        $this->extensionFactory = $extensionFactory;
    }

    /**
     * Add the parent SKU to the lines of one order.
     *
     * @param OrderRepositoryInterface $subject
     * @param OrderInterface $order
     * @return OrderInterface
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function afterGet(OrderRepositoryInterface $subject, OrderInterface $order): OrderInterface
    {
        $this->apply([$order]);
        return $order;
    }

    /**
     * Add the parent SKU to the lines of every order in a search result.
     *
     * @param OrderRepositoryInterface $subject
     * @param OrderSearchResultInterface $result
     * @return OrderSearchResultInterface
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function afterGetList(
        OrderRepositoryInterface $subject,
        OrderSearchResultInterface $result
    ): OrderSearchResultInterface {
        $this->apply($result->getItems());
        return $result;
    }

    /**
     * Resolve the configurable parent of each line, then set the attribute with one batched SKU lookup.
     *
     * @param OrderInterface[] $orders
     * @return void
     */
    private function apply(array $orders): void
    {
        $pairs = []; // [OrderItemInterface, entity_id of the configurable product]
        foreach ($orders as $order) {
            $items = $order->getItems() ?: [];
            $byId = [];
            foreach ($items as $item) {
                $byId[(int)$item->getItemId()] = $item;
            }
            foreach ($items as $item) {
                if ($item->getProductType() === Configurable::TYPE_CODE) {
                    $pairs[] = [$item, (int)$item->getProductId()];
                    continue;
                }
                $parentItem = $byId[(int)$item->getParentItemId()] ?? null;
                if ($parentItem !== null && $parentItem->getProductType() === Configurable::TYPE_CODE) {
                    $pairs[] = [$item, (int)$parentItem->getProductId()];
                }
            }
        }
        if (!$pairs) {
            return;
        }

        // sales_order_item.product_id holds entity_id (not row_id), so this lookup is staging-safe.
        $ids = array_values(array_unique(array_column($pairs, 1)));
        $skuById = [];
        foreach ($this->productResource->getProductsSku($ids) as $row) {
            $skuById[(int)$row['entity_id']] = (string)$row['sku'];
        }

        foreach ($pairs as [$item, $productId]) {
            if (!isset($skuById[$productId])) {
                continue; // The configurable product was deleted after the order: leave the attribute out.
            }
            /** @var OrderItemInterface $item */
            $extension = $item->getExtensionAttributes() ?: $this->extensionFactory->create();
            $extension->setZembulaParentSku($skuById[$productId]);
            $item->setExtensionAttributes($extension);
        }
    }
}
