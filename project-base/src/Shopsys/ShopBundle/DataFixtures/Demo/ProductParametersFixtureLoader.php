<?php

namespace Shopsys\ShopBundle\DataFixtures\Demo;

use Doctrine\Common\Collections\ArrayCollection;
use Shopsys\FrameworkBundle\Model\Product\Parameter\ParameterDataFactoryInterface;
use Shopsys\FrameworkBundle\Model\Product\Parameter\ParameterFacade;
use Shopsys\FrameworkBundle\Model\Product\Parameter\ParameterValueDataFactoryInterface;
use Shopsys\FrameworkBundle\Model\Product\Parameter\ProductParameterValueDataFactoryInterface;

class ProductParametersFixtureLoader
{
    /**
     * @var \Shopsys\FrameworkBundle\Model\Product\Parameter\ParameterFacade
     */
    protected $parameterFacade;

    /**
     * @var \Shopsys\FrameworkBundle\Model\Product\Parameter\Parameter[]
     */
    protected $parameters;

    /**
     * @var \Shopsys\FrameworkBundle\Model\Product\Parameter\ProductParameterValueDataFactoryInterface
     */
    protected $productParameterValueDataFactory;

    /**
     * @var \Shopsys\FrameworkBundle\Model\Product\Parameter\ParameterValueDataFactoryInterface
     */
    protected $parameterValueDataFactory;

    /**
     * @var \Shopsys\FrameworkBundle\Model\Product\Parameter\ParameterDataFactoryInterface
     */
    protected $parameterDataFactory;

    /**
     * @param \Shopsys\FrameworkBundle\Model\Product\Parameter\ParameterFacade $parameterFacade
     * @param \Shopsys\FrameworkBundle\Model\Product\Parameter\ProductParameterValueDataFactoryInterface $productParameterValueDataFactory
     * @param \Shopsys\FrameworkBundle\Model\Product\Parameter\ParameterValueDataFactoryInterface $parameterValueDataFactory
     * @param \Shopsys\FrameworkBundle\Model\Product\Parameter\ParameterDataFactoryInterface $parameterDataFactory
     */
    public function __construct(
        ParameterFacade $parameterFacade,
        ProductParameterValueDataFactoryInterface $productParameterValueDataFactory,
        ParameterValueDataFactoryInterface $parameterValueDataFactory,
        ParameterDataFactoryInterface $parameterDataFactory
    ) {
        $this->parameterFacade = $parameterFacade;
        $this->parameters = [];
        $this->productParameterValueDataFactory = $productParameterValueDataFactory;
        $this->parameterValueDataFactory = $parameterValueDataFactory;
        $this->parameterDataFactory = $parameterDataFactory;
    }

    /**
     * @param string|null $cellValue
     * @return \Shopsys\FrameworkBundle\Model\Product\Parameter\ProductParameterValueData[]
     */
    public function getProductParameterValuesDataFromString($cellValue)
    {
        if ($cellValue === null) {
            return [];
        }

        $parameterRows = explode(';', $cellValue);
        $productParameterValuesDataCollection = new ArrayCollection();
        foreach ($parameterRows as $parameterRow) {
            list($serializedParameterNames, $serializedValueTexts) = explode('=', $parameterRow);

            $this->addProductParameterValuesDataToCollection(
                $productParameterValuesDataCollection,
                trim($serializedParameterNames, '[]'),
                trim($serializedValueTexts, '[]')
            );
        }

        return $productParameterValuesDataCollection->toArray();
    }

    public function clearCache()
    {
        $this->parameters = [];
    }

    /**
     * @param \Doctrine\Common\Collections\ArrayCollection $productParameterValuesDataCollection
     * @param string $serializedParameterNames
     * @param string $serializedValueTexts
     */
    protected function addProductParameterValuesDataToCollection(
        ArrayCollection $productParameterValuesDataCollection,
        $serializedParameterNames,
        $serializedValueTexts
    ) {
        $parameterNames = $this->getDeserializedValuesIndexedByLocale($serializedParameterNames);
        $parameterValues = $this->getDeserializedValuesIndexedByLocale($serializedValueTexts);

        $parameter = $this->findParameterByNamesOrCreateNew($parameterNames);

        foreach ($parameterValues as $locale => $parameterValue) {
            $productParameterValueData = $this->productParameterValueDataFactory->create();
            $parameterValueData = $this->parameterValueDataFactory->create();
            $parameterValueData->text = $parameterValue;
            $parameterValueData->locale = $locale;
            $productParameterValueData->parameterValueData = $parameterValueData;
            $productParameterValueData->parameter = $parameter;

            $productParameterValuesDataCollection->add($productParameterValueData);
        }
    }

    /**
     * @param string $serializedString
     * @return string[]
     */
    protected function getDeserializedValuesIndexedByLocale($serializedString)
    {
        $values = [];
        $items = explode(',', $serializedString);
        foreach ($items as $item) {
            list($locale, $value) = explode(':', $item);
            $values[$locale] = $value;
        }

        return $values;
    }

    /**
     * @param string[] $parameterNamesByLocale
     * @return \Shopsys\FrameworkBundle\Model\Product\Parameter\Parameter
     */
    protected function findParameterByNamesOrCreateNew(array $parameterNamesByLocale)
    {
        $cacheId = json_encode($parameterNamesByLocale);

        if (isset($this->parameters[$cacheId])) {
            return $this->parameters[$cacheId];
        }

        $parameter = $this->parameterFacade->findParameterByNames($parameterNamesByLocale);

        if ($parameter === null) {
            $visible = true;
            $parameterData = $this->parameterDataFactory->create();
            $parameterData->name = $parameterNamesByLocale;
            $parameterData->visible = $visible;
            $parameter = $this->parameterFacade->create($parameterData);
        }

        $this->parameters[$cacheId] = $parameter;

        return $parameter;
    }
}
