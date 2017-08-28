<?php namespace Mpociot\Couchbase\Serialization;

/**
 * Class IGBinarySerializer
 * @package Mpociot\Couchbase\Serialize
 */
class IGBinarySerializer implements SerializerInterface
{
    /**
     * @param mixed $value
     * @return string
     */
    public function encode($value): string {
        return igbinary_serialize($value);
    }

    /**
     * @param string $value
     * @return mixed
     */
    public function decode(string $value) {
        return igbinary_unserialize($value);
    }
}