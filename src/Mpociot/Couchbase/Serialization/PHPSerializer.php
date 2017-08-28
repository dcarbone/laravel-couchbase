<?php namespace Mpociot\Couchbase\Serialization;

/**
 * Class PHPSerializer
 * @package Mpociot\Couchbase\Serialize
 */
class PHPSerializer implements SerializerInterface
{
    /**
     * @param mixed $value
     * @return string
     */
    public function encode($value): string {
        return serialize($value);
    }

    /**
     * @param string $value
     * @return mixed
     */
    public function decode(string $value) {
        return unserialize($value);
    }
}