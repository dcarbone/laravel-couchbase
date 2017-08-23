<?php namespace Mpociot\Couchbase;

/**
 * Class CouchbaseINI
 * @package Mpociot\Couchbase
 */
class CouchbaseINI {

    /** @var array */
    private static $inis = [];

    /** @var bool */
    private static $init = false;

    /**
     * @internal
     */
    public static function _init() {
        if (!self::$init) {
            self::$inis = ini_get_all('couchbase', false);
            self::$init = true;
        }
    }

    /**
     * @param string $key
     * @return null|string
     */
    public static function get(string $key): ?string {
        return self::$inis[$key] ?? null;
    }

    /**
     * @return null|string
     */
    public static function serializer(): ?string {
        return self::$inis['couchbase.serializer'] ?? null;
    }

    /**
     * @return bool
     */
    public static function decoderJSONArrays(): bool {
        return self::$inis['couchbase.decoder.json_arrays'] ?? false;
    }
}
CouchbaseINI::_init();