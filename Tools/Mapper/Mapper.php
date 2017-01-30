<?php

namespace Smartbox\Integration\FrameworkBundle\Tools\Mapper;

use JMS\Serializer\SerializationContext;
use Smartbox\Integration\FrameworkBundle\DependencyInjection\Traits\UsesEvaluator;
use Smartbox\Integration\FrameworkBundle\Tools\Helper\DateTimeHelper;

/**
 * Class Mapper.
 */
class Mapper implements MapperInterface
{
    use UsesEvaluator;

    protected $debug = false;

    protected $mappings = [];

    protected $dictionary = [
        'ISO8601' => \DateTime::ISO8601,
        'ISO8601Micro' => 'Y-m-d\TH:i:s.000',
    ];

    public function setDebug($debug = false)
    {
        $this->debug = $debug;
    }

    public function addMappings(array $mappings)
    {
        foreach ($mappings as $mappingName => $mapping) {
            $this->mappings[$mappingName] = $mapping;
        }
    }

    /**
     * @param $format
     * @param \DateTime|null $date
     *
     * @return null|string
     */
    public function formatDate($format, \DateTime $date = null)
    {
        if ($date === null) {
            return;
        }

        return $date->format($format);
    }

    /**
     * @param mixed  $obj
     * @param string $mappingName
     * @param mixed  $context
     *
     * @return array|mixed
     */
    public function map($obj, $mappingName, $context = [])
    {
        if (!$mappingName || !array_key_exists($mappingName, $this->mappings)) {
            throw new \InvalidArgumentException(sprintf('Invalid mapping name "%s"', $mappingName));
        }

        if (empty($obj)) {
            return $obj;
        }

        $mapping = @$this->mappings[$mappingName];

        return $this->resolve($mapping,$obj, $context);
    }

    /***
     * @param array|string $mapping
     * @param mixed $obj
     * @return array|string
     */
    public function resolve(&$mapping, &$obj, &$context)
    {
        if (empty($mapping)) {
            return $mapping;
        } elseif (is_array($mapping)) {
            $res = [];
            foreach ($mapping as $key => $value) {
                $resolved = $this->resolve($value, $obj, $context);

                if ($resolved !== null && $resolved !== []) {
                    $res[$key] = $resolved;
                }
            }

            return $res;
        } elseif (is_string($mapping)) {
            $dictionary = array_merge($this->dictionary, ['obj' => $obj, 'context' => $context]);
            $res = null;

            try {
                $res = @$this->evaluator->evaluateWithVars($mapping, $dictionary);
            } catch(\RuntimeException $e) {
                if ($this->debug) {
                    throw $e;
                }
            }
            return $res;
        }

        throw new \RuntimeException("Mapper expected the mapping to be a string or an array");
    }


    /**
     * @param array  $elements
     * @param string $mappingName
     * @param mixed  $context
     *
     * @return array
     */
    public function mapAll(array $elements, $mappingName, $context = [])
    {
        if (empty($elements)) {
            return $elements;
        }

        $res = [];
        foreach ($elements as $key => $element) {
            $res[$key] = $this->map($element, $mappingName, $context);
        }

        return $res;
    }

    /**
     * Return true if the key exists in the given array
     *
     * @param array $obj
     * @param string $key
     *
     * @return bool
     */
    public function keyExists(array $obj, $key)
    {
        return array_key_exists($key, $obj);
    }

    public function propertyExists($class, $property)
    {
        return property_exists($class, $property);
    }

    /**
     * Get the first element of an array.
     *
     * @param array $array
     *
     * @return mixed
     */
    public function first(array $array)
    {
        return reset($array);
    }

    /**
     * Convert an string to date.
     *
     * @param string $date
     *
     * @return \DateTime
     */
    public function stringToDate($date)
    {
        return new \DateTime($date);
    }

    public function timestampToDate($timestamp)
    {
        $date = new \DateTime();
        $date->setTimestamp($timestamp);

        return $date;
    }

    public function timestampWithMsToDate($timestamp)
    {
        return DateTimeHelper::createDateTimeFromTimestampWithMilliseconds($timestamp);
    }

    /**
     * Create a Soap var object.
     *
     * @param mixed  $data     Data to create the SoapVar object
     * @param string $encoding The encoding id
     * @param string $type     Entity type name
     *
     * @return \SoapVar
     */
    public function toSoapVarObj($data, $encoding, $type = null)
    {
        return new \SoapVar($data, $encoding, $type);
    }

    /**
     * Convert an array into string.
     *
     * @param string $glue The string to use to glue the elements of the array
     * @param array  $data The array of strings to join
     *
     * @return string
     */
    public function arrayToString($glue, array $data)
    {
        return implode($glue, $data);
    }

    /**
     * Flatten an array by key
     *
     * @param array $data The array to flatten
     * @param string $key The common key
     *
     * @return array
     */
    public function flattenArrayByKey(array $data, $key)
    {
        $array = [];
        foreach ($data as $value) {
            if (is_array($value) && isset($value[$key])) {
                $array[] = $value[$key];
            }
        }

        return $array;
    }

    public function toString($data)
    {
        return (string) $data;
    }

    public function toMongoID($id)
    {
        if (class_exists('\MongoDB\BSON\ObjectID')) {
            return new \MongoDB\BSON\ObjectID((string) $id);
        }

        throw new \RuntimeException('To instantiate a Mongo ObjectID object you need to install the php mongo extension.');
    }

    /**
     * Serialize the given datas into the expected format with a group
     *
     * @param $data
     * @param $format
     * @param $group
     *
     * @return string
     */
    public function serializeWithGroup($data, $format, $group)
    {
        $serializer = $this->evaluator->getSerializer();
        return $serializer->serialize($data, $format, SerializationContext::create()->setGroups([$group]));
    }
}
