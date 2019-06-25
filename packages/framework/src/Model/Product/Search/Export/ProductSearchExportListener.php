<?php

declare(strict_types=1);

namespace Shopsys\FrameworkBundle\Model\Product\Search\Export;

use Doctrine\ORM\EntityManagerInterface;
use Shopsys\Environment;
use Shopsys\FrameworkBundle\Component\Environment\EnvironmentType;
use Symfony\Component\HttpKernel\Event\FilterResponseEvent;

class ProductSearchExportListener
{
    /**
     * @var \Doctrine\ORM\EntityManagerInterface
     */
    protected $entityManager;

    /**
     * @var \Shopsys\FrameworkBundle\Model\Product\Search\Export\ProductSearchExportFacade
     */
    protected $productSearchExportFacade;

    /**
     * @var \Shopsys\FrameworkBundle\Model\Product\Search\Export\ProductSearchExportScheduler
     */
    protected $productSearchExportScheduler;

    /**
     * @var string
     */
    protected $environment;

    /**
     * @param string $environment
     * @param \Shopsys\FrameworkBundle\Model\Product\Search\Export\ProductSearchExportScheduler $productSearchExportScheduler
     * @param \Shopsys\FrameworkBundle\Model\Product\Search\Export\ProductSearchExportFacade $productSearchExportFacade
     * @param \Doctrine\ORM\EntityManagerInterface $entityManager
     */
    public function __construct(
        string $environment,
        ProductSearchExportScheduler $productSearchExportScheduler,
        ProductSearchExportFacade $productSearchExportFacade,
        EntityManagerInterface $entityManager
    ) {
        $this->productSearchExportScheduler = $productSearchExportScheduler;
        $this->productSearchExportFacade = $productSearchExportFacade;
        $this->entityManager = $entityManager;
        $this->environment = $environment;
    }

    /**
     * @param \Symfony\Component\HttpKernel\Event\FilterResponseEvent $filterResponseEvent
     */
    public function onKernelResponse(FilterResponseEvent $filterResponseEvent): void
    {
        // ElasticSearch does not support transactions so in tests is this functionality disabled in order to prevent changes of data
        if ($this->environment !== EnvironmentType::TEST) {
            if ($this->productSearchExportScheduler->hasAnyProductIdsForImmediateExport()) {
                // to be sure the recalculated data are fetched from database properly
                $this->entityManager->clear();

                $productIds = $this->productSearchExportScheduler->getProductIdsForImmediateExport();
                $this->productSearchExportFacade->exportIds($productIds);
            }
        }
    }
}
