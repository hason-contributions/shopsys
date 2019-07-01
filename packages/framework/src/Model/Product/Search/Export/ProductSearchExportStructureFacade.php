<?php

declare(strict_types=1);

namespace Shopsys\FrameworkBundle\Model\Product\Search\Export;

use BadMethodCallException;
use Shopsys\FrameworkBundle\Component\Domain\Domain;
use Shopsys\FrameworkBundle\Component\Elasticsearch\ElasticsearchStructureManager;
use Shopsys\FrameworkBundle\Component\Elasticsearch\ElasticsearchStructureUpdateChecker;
use Shopsys\FrameworkBundle\Model\Product\Search\ProductElasticsearchRepository;
use Symfony\Component\Console\Output\OutputInterface;

class ProductSearchExportStructureFacade
{
    /**
     * @var \Shopsys\FrameworkBundle\Component\Domain\Domain
     */
    protected $domain;

    /**
     * @var \Shopsys\FrameworkBundle\Component\Elasticsearch\ElasticsearchStructureManager
     */
    protected $elasticsearchStructureManager;

    /**
     * @var \Shopsys\FrameworkBundle\Component\Elasticsearch\ElasticsearchStructureUpdateChecker
     */
    protected $elasticsearchStructureUpdateChecker;

    /**
     * @param \Shopsys\FrameworkBundle\Component\Domain\Domain $domain
     * @param \Shopsys\FrameworkBundle\Component\Elasticsearch\ElasticsearchStructureManager $elasticsearchStructureManager
     */
    public function __construct(Domain $domain, ElasticsearchStructureManager $elasticsearchStructureManager)
    {
        $this->domain = $domain;
        $this->elasticsearchStructureManager = $elasticsearchStructureManager;
    }

    /**
     * @required
     * @param \Shopsys\FrameworkBundle\Component\Elasticsearch\ElasticsearchStructureUpdateChecker $elasticsearchStructureUpdateChecker
     * @internal Will be replaced with constructor injection in the next major release
     */
    public function setElasticsearchStructureUpdateChecker(ElasticsearchStructureUpdateChecker $elasticsearchStructureUpdateChecker): void
    {
        if ($this->elasticsearchStructureUpdateChecker !== null) {
            throw new BadMethodCallException(sprintf('Method "%s" has been already called and cannot be called multiple times.', __METHOD__));
        }

        $this->elasticsearchStructureUpdateChecker = $elasticsearchStructureUpdateChecker;
    }

    /**
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     */
    public function reindexIndexesIfNecessary(OutputInterface $output)
    {
        foreach ($this->domain->getAllIds() as $domainId) {
            $output->writeln(sprintf('Reindexing index for domain with ID %s', $domainId));

            $index = ProductElasticsearchRepository::ELASTICSEARCH_INDEX;
            $aliasName = $this->elasticsearchStructureManager->getAliasName($domainId, $index);
            $definition = $this->elasticsearchStructureManager->getStructureDefinition($domainId, $index);

            if ($this->elasticsearchStructureUpdateChecker->isNecessaryToUpdateStructure($definition, $aliasName)) {
                $this->elasticsearchStructureManager->createIndex($domainId, $index);
                $this->elasticsearchStructureManager->reindexFromCurrentIndexToNewIndex($domainId, $index);
                $this->elasticsearchStructureManager->deleteNotUsedIndexes($aliasName, $domainId);
                $this->elasticsearchStructureManager->createAliasForIndex($domainId, $index);
                $output->writeln(sprintf('Reindex done'));
            } else {
                $output->writeln(sprintf('Reindex is not necessary as there were no changes in the definition'));
            }
        }
    }

    /**
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     */
    public function createIndexes(OutputInterface $output)
    {
        foreach ($this->domain->getAllIds() as $domainId) {
            $this->createIndexIfNecessary($output, $domainId);
            $this->createAlias($output, $domainId);
        }
    }

    /**
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     * @param int $domainId
     */
    protected function createAlias(OutputInterface $output, int $domainId)
    {
        $output->writeln(sprintf('Creating alias for domain with ID %s', $domainId));

        $this->elasticsearchStructureManager->createAliasForIndex(
            $domainId,
            ProductElasticsearchRepository::ELASTICSEARCH_INDEX
        );

        $output->writeln('Alias created');
    }

    /**
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     * @param int $domainId
     */
    protected function createIndexIfNecessary(OutputInterface $output, int $domainId)
    {
        $output->writeln(sprintf('Creating index for domain with ID %s', $domainId));

        $index = ProductElasticsearchRepository::ELASTICSEARCH_INDEX;
        $aliasName = $this->elasticsearchStructureManager->getAliasName($domainId, $index);
        $definition = $this->elasticsearchStructureManager->getStructureDefinition($domainId, $index);
        if ($this->elasticsearchStructureUpdateChecker->isNecessaryToUpdateStructure($definition, $aliasName)) {
            $this->elasticsearchStructureManager->createIndexAndSetToSettings($domainId, $index);
        }

        $output->writeln('Index created');
    }

    /**
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     */
    public function deleteIndexes(OutputInterface $output)
    {
        foreach ($this->domain->getAllIds() as $domainId) {
            $output->writeln(sprintf('Deleting index for domain with ID %s', $domainId));
            $this->elasticsearchStructureManager->deleteNotUsedIndexes(
                $this->elasticsearchStructureManager->getAliasName($domainId, ProductElasticsearchRepository::ELASTICSEARCH_INDEX),
                $domainId
            );
        }
    }
}
