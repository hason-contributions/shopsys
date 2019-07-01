<?php

declare(strict_types=1);

namespace Shopsys\FrameworkBundle\Component\Elasticsearch;

use Elasticsearch\Client;
use Shopsys\FrameworkBundle\Component\ArrayUtils\RecursiveArraySorter;

class ElasticsearchStructureUpdateChecker
{
    /**
     * @var \Elasticsearch\Client
     */
    protected $client;

    /**
     * @var \Shopsys\FrameworkBundle\Component\Elasticsearch\ElasticsearchStructureManager
     */
    protected $elasticsearchStructureManager;

    /**
     * @var \Shopsys\FrameworkBundle\Component\ArrayUtils\RecursiveArraySorter
     */
    protected $recursiveArraySorter;

    /**
     * @param \Elasticsearch\Client $client
     * @param \Shopsys\FrameworkBundle\Component\Elasticsearch\ElasticsearchStructureManager $elasticsearchStructureManager
     * @param \Shopsys\FrameworkBundle\Component\ArrayUtils\RecursiveArraySorter $recursiveArraySorter
     */
    public function __construct(
        Client $client,
        ElasticsearchStructureManager $elasticsearchStructureManager,
        RecursiveArraySorter $recursiveArraySorter
    ) {
        $this->client = $client;
        $this->elasticsearchStructureManager = $elasticsearchStructureManager;
        $this->recursiveArraySorter = $recursiveArraySorter;
    }

    /**
     * @param array $definition
     * @param string $aliasName
     * @return bool
     */
    public function isNecessaryToUpdateStructure(array $definition, string $aliasName): bool
    {
        $indexes = $this->client->indices();

        $indexNames = $this->elasticsearchStructureManager->getExistingIndexNamesForAlias($aliasName);

        if (count($indexNames) === 0) {
            return true;
        }

        foreach ($indexNames as $indexName) {
            $currentIndex = $indexes->get(['index' => $indexName]);
            $currentIndexDefinition = $this->prepareCurrentIndexDefinitionForComparing(reset($currentIndex));

            $this->recursiveArraySorter->recursiveArrayKsort($currentIndexDefinition);
            $this->recursiveArraySorter->recursiveArrayKsort($definition);

            if ($currentIndexDefinition !== $definition) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array $indexDefinition
     * @return array
     */
    protected function prepareCurrentIndexDefinitionForComparing(array $indexDefinition): array
    {
        $indexDefinition['settings'] = $indexDefinition['settings']['index'];
        $indexDefinition['settings']['index'] = [
            'number_of_replicas' => (int)$indexDefinition['settings']['number_of_replicas'],
            'number_of_shards' => (int)$indexDefinition['settings']['number_of_shards'],
        ];
        $indexDefinition['settings']['analysis']['filter']['edge_ngram']['max_gram'] =
            (int)$indexDefinition['settings']['analysis']['filter']['edge_ngram']['max_gram'];
        $indexDefinition['settings']['analysis']['filter']['edge_ngram']['min_gram'] =
            (int)$indexDefinition['settings']['analysis']['filter']['edge_ngram']['min_gram'];
        unset(
            $indexDefinition['aliases'],
            $indexDefinition['settings']['provided_name'],
            $indexDefinition['settings']['creation_date'],
            $indexDefinition['settings']['uuid'],
            $indexDefinition['settings']['version'],
            $indexDefinition['settings']['number_of_replicas'],
            $indexDefinition['settings']['number_of_shards']
        );
        return $indexDefinition;
    }
}
