<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Magento\CatalogMessageBroker\Model\MessageBus\ProductVariants;

use Magento\CatalogMessageBroker\Model\FetchProductVariantsInterface;
use Magento\CatalogMessageBroker\Model\MessageBus\ConsumerEventInterface;
use Magento\CatalogMessageBroker\Model\ServiceConfig;
use Magento\CatalogStorefrontApi\Api\Data\ImportVariantsRequestInterfaceFactory;
use Magento\CatalogStorefrontApi\Api\Data\ProductVariantImportInterface;
use Magento\CatalogStorefrontApi\Api\Data\ProductVariantImportMapper;
use Magento\MessageBroker\Model\ServiceConnector\Connector;
use Psr\Log\LoggerInterface;

/**
 * Publish product variants into storage
 */
class PublishProductVariantsConsumer implements ConsumerEventInterface
{
    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var FetchProductVariantsInterface
     */
    private $fetchProductVariants;

    /**
     * @var Connector
     */
    private $connector;

    /**
     * @var ImportVariantsRequestInterfaceFactory
     */
    private $importVariantsRequestInterfaceFactory;

    /**
     * @var ProductVariantImportMapper
     */
    private $productVariantImportMapper;

    /**
     * @param LoggerInterface $logger
     * @param FetchProductVariantsInterface $fetchProductVariants
     * @param Connector $connector
     * @param ImportVariantsRequestInterfaceFactory $importVariantsRequestInterfaceFactory
     * @param ProductVariantImportMapper $productVariantImportMapper
     */
    public function __construct(
        LoggerInterface $logger,
        FetchProductVariantsInterface $fetchProductVariants,
        Connector $connector,
        ImportVariantsRequestInterfaceFactory $importVariantsRequestInterfaceFactory,
        ProductVariantImportMapper $productVariantImportMapper
    ) {
        $this->logger = $logger;
        $this->fetchProductVariants = $fetchProductVariants;
        $this->connector = $connector;
        $this->importVariantsRequestInterfaceFactory = $importVariantsRequestInterfaceFactory;
        $this->productVariantImportMapper = $productVariantImportMapper;
    }

    /**
     * @inheritdoc
     */
    public function execute(array $entities, ?string $scope = null): void
    {
        $variantsData = $this->fetchProductVariants->execute($entities);
        $variantsImportData = [];
        foreach ($variantsData as $variantData) {
            $variantsImportData[] = $this->buildVariantImportObj($variantData);
        }
        if (!empty($variantsImportData)) {
            $this->importVariants($variantsImportData);
        }
    }

    /**
     * Build product variant import object
     *
     * @param array $variantsData
     * @return ProductVariantImportInterface
     */
    private function buildVariantImportObj(array $variantsData): ProductVariantImportInterface
    {
        return $this->productVariantImportMapper->setData(
            [
                'id' => $variantsData['id'],
                'option_values' => $variantsData['option_values'],
            ]
        )->build();
    }

    /**
     * Import variants
     *
     * @param ProductVariantImportInterface[] $variants
     * @return void
     */
    private function importVariants(array $variants): void
    {
        $importVariantsRequest = $this->importVariantsRequestInterfaceFactory->create();
        $importVariantsRequest->setVariants($variants);

        try {
            $importResult = $this->connector
                ->getConnection(ServiceConfig::SERVICE_NAME_VARIANTS)
                ->importProductVariants($importVariantsRequest);

            if ($importResult->getStatus() === false) {
                $this->logger->error(sprintf('Product variants import failed: "%s"', $importResult->getMessage()));
            }
        } catch (\Throwable $e) {
            $this->logger->critical(sprintf('Exception while publishing product variants: "%s"', $e));
        }
    }
}
