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
            self::$client = new \MongoDB\Client('mongodb://localhost:27017', [], [
                'typeMap' => [
                    'root'     => 'array',
                    'document' => 'array',
                    'array'    => 'array',
                ]
            ]);
        }

        return self::$client;
    }
}
