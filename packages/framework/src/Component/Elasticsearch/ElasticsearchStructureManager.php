<?php

namespace Shopsys\FrameworkBundle\Component\Elasticsearch;

use BadMethodCallException;
use Elasticsearch\Client;
use Shopsys\FrameworkBundle\Component\Elasticsearch\Exception\ElasticsearchStructureException;
use Shopsys\FrameworkBundle\Component\Setting\Setting;

class ElasticsearchStructureManager
{
    /**
     * @var string
     */
    protected $definitionDirectory;

    /**
     * @var string
     */
    protected $indexPrefix;

    /**
     * @var \Elasticsearch\Client
     */
    protected $client;

    /**
     * @var int|null
     */
    protected $buildVersion;

    /**
     * @var \Shopsys\FrameworkBundle\Component\Setting\Setting|null
     */
    protected $setting;

    /**
     * @param string $definitionDirectory
     * @param string $indexPrefix
     * @param \Elasticsearch\Client $client
     */
    public function __construct(string $definitionDirectory, string $indexPrefix, Client $client)
    {
        $this->definitionDirectory = $definitionDirectory;
        $this->indexPrefix = $indexPrefix;
        $this->client = $client;
    }

    /**
     * @param int $buildVersion
     * @internal Will be replaced with constructor injection in the next major release
     */
    public function setBuildVersion(int $buildVersion): void
    {
        if ($this->buildVersion !== null) {
            throw new BadMethodCallException(sprintf('Method "%s" has been already called and cannot be called multiple times.', __METHOD__));
        }

        $this->buildVersion = $buildVersion;
    }

    /**
     * @required
     * @param \Shopsys\FrameworkBundle\Component\Setting\Setting $setting
     * @internal Will be replaced with constructor injection in the next major release
     */
    public function setSetting(Setting $setting): void
    {
        if ($this->setting !== null) {
            throw new BadMethodCallException(sprintf('Method "%s" has been already called and cannot be called multiple times.', __METHOD__));
        }

        $this->setting = $setting;
    }

    /**
     * @param int $domainId
     * @param string $index
     * @return string
     * @deprecated Getting index name without a version using this method is deprecated since SSFW 7.3, use getVersionedIndexName or getAliasName instead
     */
    public function getIndexName(int $domainId, string $index): string
    {
        @trigger_error(
            sprintf('Getting index name without a version using method "%s" is deprecated since SSFW 7.3, use %s or %s instead', __METHOD__, 'getVersionedIndexName', 'getAliasName'),
            E_USER_DEPRECATED
        );
        return $this->indexPrefix . $index . $domainId;
    }

    /**
     * @param int $domainId
     * @param string $index
     * @return string
     */
    public function getVersionedIndexName(int $domainId, string $index): string
    {
        return $this->indexPrefix . $this->buildVersion . $index . $domainId;
    }

    /**
     * @param int $domainId
     * @return string
     */
    public function getCurrentIndexName(int $domainId): string
    {
        return $this->setting->getForDomain(Setting::ELASTICSEARCH_INDEX, $domainId);
    }

    /**
     * @param int $domainId
     * @param string $index
     * @return string
     */
    public function getAliasName(int $domainId, string $index): string
    {
        return $this->indexPrefix . $index . $domainId . 'alias';
    }

    /**
     * @param int $domainId
     * @param string $index
     */
    public function createIndex(int $domainId, string $index)
    {
        $definition = $this->getStructureDefinition($domainId, $index);
        $indexes = $this->client->indices();
        $indexName = $this->getVersionedIndexName($domainId, $index);
        if ($indexes->exists(['index' => $indexName])) {
            throw new ElasticsearchStructureException(sprintf('Index %s already exists', $indexName));
        }

        $indexes->create([
            'index' => $indexName,
            'body' => $definition,
        ]);
    }

    /**
     * @param int $domainId
     * @param string $index
     */
    public function createIndexAndSetToSettings(int $domainId, string $index)
    {
        $this->createIndex($domainId, $index);
        $indexName = $this->getVersionedIndexName($domainId, $index);
        $this->setting->setForDomain(Setting::ELASTICSEARCH_INDEX, $indexName, $domainId);
    }

    /**
     * @param int $domainId
     * @param string $index
     * @deprecated Deleting index using this method is deprecated since SSFW 7.3, use deleteIndexesOfCurrentAlias instead
     */
    public function deleteIndex(int $domainId, string $index)
    {
        @trigger_error(
            sprintf('Deleting index using method "%s" is deprecated since SSFW 7.3, use %s instead', __METHOD__, 'deleteIndexesOfCurrentAlias'),
            E_USER_DEPRECATED
        );

        $indexes = $this->client->indices();
        $indexName = $this->getIndexName($domainId, $index);
        if ($indexes->exists(['index' => $indexName])) {
            $indexes->delete(['index' => $indexName]);
        }
    }

    /**
     * @param int $domainId
     * @param string $index
     */
    public function reindexFromCurrentIndexToNewIndex(int $domainId, string $index)
    {
        $indexes = $this->client->indices();
        $indexName = $this->getVersionedIndexName($domainId, $index);
        if ($indexes->exists(['index' => $this->getCurrentIndexName($domainId)]) && $indexes->exists(['index' => $indexName])) {
            $body = [
                'source' => [
                    'index' => $this->getCurrentIndexName($domainId),
                ],
                'dest' => [
                    'index' => $indexName,
                ],
            ];

            $this->client->reindex([
                'body' => $body,
            ]);
            $this->setting->setForDomain(Setting::ELASTICSEARCH_INDEX, $indexName, $domainId);
        }
    }

    /**
     * @param int $domainId
     * @param string $index
     */
    public function createAliasForIndex(int $domainId, string $index)
    {
        $indexes = $this->client->indices();
        $aliasName = $this->getAliasName($domainId, $index);
        $indexName = $this->getVersionedIndexName($domainId, $index);

        if (!$indexes->existsAlias(['name' => $aliasName, 'index' => $indexName]) && $indexes->exists(['index' => $indexName])) {
            $indexes->putAlias([
                'index' => $indexName,
                'name' => $aliasName,
            ]);
        }
    }

    /**
     * @param string $aliasName
     * @param int $domainId
     */
    public function deleteNotUsedIndexes(string $aliasName, int $domainId)
    {
        $indexes = $this->client->indices();

        foreach ($this->getExistingIndexNamesForAlias($aliasName) as $indexName) {
            if ($indexName !== $this->getCurrentIndexName($domainId)) {
                $indexes->deleteAlias([
                    'index' => $indexName,
                    'name' => $aliasName,
                ]);

                $indexes->delete(['index' => $indexName]);
            }
        }
    }

    /**
     * @param string $aliasName
     * @return array
     */
    public function getExistingIndexNamesForAlias(string $aliasName): array
    {
        $existingIndexNames = [];
        $indexes = $this->client->indices();

        if ($indexes->existsAlias(['name' => $aliasName])) {
            $aliases = $indexes->getAlias([
                'name' => $aliasName,
            ]);
            foreach (array_keys($aliases) as $indexName) {
                if ($indexes->exists(['index' => $indexName])) {
                    $existingIndexNames[] = $indexName;
                }
            }
        }

        return $existingIndexNames;
    }

    /**
     * @param int $domainId
     * @param string $index
     * @return array
     * @deprecated using method getDefinition is deprecated since SSFW 7.3. Use public method getStructureDefinition instead
     */
    protected function getDefinition(int $domainId, string $index): array
    {
        $file = sprintf('%s/%s/%s.json', $this->definitionDirectory, $index, $domainId);
        if (!is_file($file)) {
            throw new ElasticsearchStructureException(
                sprintf(
                    'Definition file %d.json, for domain ID %1$d, not found in definition folder "%s".' . PHP_EOL . 'Please make sure that for each domain exists a definition json file named by the corresponding domain ID.',
                    $domainId,
                    $this->definitionDirectory
                )
            );
        }
        $json = file_get_contents($file);

        $definition = json_decode($json, JSON_OBJECT_AS_ARRAY);
        if ($definition === null) {
            throw new ElasticsearchStructureException(sprintf('Invalid JSON format in file %s', $file));
        }

        return $definition;
    }

    /**
     * @param int $domainId
     * @param string $index
     * @return array
     */
    public function getStructureDefinition(int $domainId, string $index): array
    {
        return $this->getDefinition($domainId, $index);
    }
}
