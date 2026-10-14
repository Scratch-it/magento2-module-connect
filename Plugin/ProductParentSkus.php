<?php
/**
 * Copyright (c) Zembula. Licensed under the MIT License; see LICENSE.
 */
declare(strict_types=1);

namespace Zembula\Connect\Plugin;

use Magento\Catalog\Api\Data\ProductExtensionFactory;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Api\Data\ProductSearchResultsInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product\Type as ProductType;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\EntityManager\MetadataPool;

/**
 * Adds extension_attributes.zembula_parent_skus to products returned by the product repository.
 *
 * REST GET /V1/products and /V1/products/:sku list, for each simple or virtual product, the SKUs of the
 * configurable products it belongs to. REST reads are not filtered by "Display Out of Stock Products".
 * One SQL query per get() / getList() call.
 */
class ProductParentSkus
{
    /**
     * @var ResourceConnection
     */
    private $resource;

    /**
     * @var MetadataPool
     */
    private $metadataPool;

    /**
     * @var ProductExtensionFactory
     */
    private $extensionFactory;

    /**
     * @param ResourceConnection $resource
     * @param MetadataPool $metadataPool
     * @param ProductExtensionFactory $extensionFactory
     */
    public function __construct(
        ResourceConnection $resource,
        MetadataPool $metadataPool,
        ProductExtensionFactory $extensionFactory
    ) {
        $this->resource = $resource;
        $this->metadataPool = $metadataPool;
        $this->extensionFactory = $extensionFactory;
    }

    /**
     * Add the parent SKUs to one product.
     *
     * @param ProductRepositoryInterface $subject
     * @param ProductInterface $product
     * @return ProductInterface
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function afterGet(ProductRepositoryInterface $subject, ProductInterface $product): ProductInterface
    {
        $this->apply([$product]);
        return $product;
    }

    /**
     * Add the parent SKUs to every product in a search result.
     *
     * @param ProductRepositoryInterface $subject
     * @param ProductSearchResultsInterface $result
     * @return ProductSearchResultsInterface
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function afterGetList(
        ProductRepositoryInterface $subject,
        ProductSearchResultsInterface $result
    ): ProductSearchResultsInterface {
        $this->apply($result->getItems());
        return $result;
    }

    /**
     * Look up the configurable parents of the simple and virtual products in one query and set the attribute.
     *
     * @param ProductInterface[] $products
     * @return void
     */
    private function apply(array $products): void
    {
        $childTypes = [ProductType::TYPE_SIMPLE, ProductType::TYPE_VIRTUAL];
        $ids = [];
        foreach ($products as $product) {
            if ($product->getId() && in_array($product->getTypeId(), $childTypes, true)) {
                $ids[] = (int)$product->getId();
            }
        }
        if (!$ids) {
            return;
        }

        // catalog_product_super_link.parent_id holds the link field: entity_id on Magento Open Source,
        // row_id on Adobe Commerce with content staging. product_id is always the child's entity_id.
        $linkField = $this->metadataPool->getMetadata(ProductInterface::class)->getLinkField();
        $connection = $this->resource->getConnection();
        $select = $connection->select()
            ->from(
                ['link' => $this->resource->getTableName('catalog_product_super_link')],
                ['child_id' => 'link.product_id']
            )
            ->join(
                ['parent' => $this->resource->getTableName('catalog_product_entity')],
                'parent.' . $linkField . ' = link.parent_id',
                ['parent_sku' => 'parent.sku']
            )
            ->where('link.product_id IN (?)', $ids, \Zend_Db::INT_TYPE);

        $parentSkus = [];
        foreach ($connection->fetchAll($select) as $row) {
            $parentSkus[(int)$row['child_id']][] = (string)$row['parent_sku'];
        }

        foreach ($products as $product) {
            $skus = $parentSkus[(int)$product->getId()] ?? null;
            if ($skus === null) {
                continue;
            }
            $extension = $product->getExtensionAttributes() ?: $this->extensionFactory->create();
            $extension->setZembulaParentSkus(array_values(array_unique($skus)));
            $product->setExtensionAttributes($extension);
        }
    }
}
