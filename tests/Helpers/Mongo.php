<?php

use MongoDB\Client;

class Mongo
{
    protected static $client;

    private function __construct()
    {
    }

    public static function client()
    {
        if (is_null(self::$client)) {
            self::$client = new \MongoDB\Client('mongodb://localhost:27017');
        }

        return self::$client;
    }
}
