<?php
/**
 * Created by PhpStorm.
 * User: sascha.presnac
 * Date: 20.04.2017
 * Time: 13:23
 */

namespace Mpociot\Couchbase;

class Helper
{
    const TYPE_NAME = 'eloquent_type';

    public static function getUniqueId($prefix = null)
    {
        return (($prefix !== null) ? $prefix.'::' : '').uniqid();
    }
}
