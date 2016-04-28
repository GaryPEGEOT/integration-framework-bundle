<?php

namespace Smartbox\Integration\FrameworkBundle\Core\Producers;

use JMS\Serializer\SerializerInterface;
use Smartbox\Integration\FrameworkBundle\Configurability\ConfigurableInterface;
use Smartbox\Integration\FrameworkBundle\Tools\Evaluator\ExpressionEvaluator;

/**
 * Interface ConfigurableProducerInterface.
 */
interface ConfigurableProducerInterface extends ProducerInterface, ConfigurableInterface
{
    /**
     * @param array $configuration
     */
    public function setMethodsConfiguration(array $configuration);

    /**
     * @param array $mappings
     */
    public function setOptions(array $mappings);

    /**
     * @param ExpressionEvaluator $evaluator
     */
    public function setEvaluator(ExpressionEvaluator $evaluator);

    /**
     * @param SerializerInterface $serializer
     */
    public function setSerializer(SerializerInterface $serializer);
}