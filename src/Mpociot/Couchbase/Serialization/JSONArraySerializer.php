<?php namespace Mpociot\Couchbase\Serialization;

/**
 * Class JSONArraySerializer
 * @package Mpociot\Couchbase\Serialize
 */
class JSONArraySerializer implements SerializerInterface
{
    /**
     * @param mixed $value
     * @return string
     */
    public function encode($value): string {
        return json_encode($value);
    }

    /**
     * @param string $value
     * @return mixed
     */
    public function decode(string $value) {
        return json_encode($value);
    }
}