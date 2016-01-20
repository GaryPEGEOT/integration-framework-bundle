<?php

namespace Smartbox\Integration\FrameworkBundle\Messages\Traits;

use JMS\Serializer\Annotation as JMS;
use Smartbox\CoreBundle\Type\SerializableArray;

/**
 * Trait HasProcessingContext
 * @package Smartbox\Integration\FrameworkBundle\Messages\Traits
 */
trait HasProcessingContext
{
    /**
     * @var null|SerializableArray
     * @JMS\Type("Smartbox\CoreBundle\Type\SerializableArray")
     * @JMS\Expose
     * @JMS\Groups({"context", "metadata"})
     */
    protected $processingContext;

    /**
     * @return null|SerializableArray
     */
    public function getProcessingContext()
    {
        return $this->processingContext;
    }

    /**
     * @param null|SerializableArray $processingContext
     */
    public function setProcessingContext($processingContext)
    {
        $this->processingContext = $processingContext;
    }
}
