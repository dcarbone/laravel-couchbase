<?php namespace Mpociot\Couchbase\Serialization;

/**
 * Interface SerializerInterface
 * @package Mpociot\Couchbase\Serialize
 */
interface SerializerInterface
{
    const PHP = 'php';
    const JSON = 'json';
    const JSON_ARRAY = 'json_array';
    const IGBINARY = 'igbinary';

    /**
     * @param mixed $value
     * @return string
     */
    public function encode($value): string;

    /**
     * @param string $value
     * @return mixed
     */
    public function decode(string $value);
}