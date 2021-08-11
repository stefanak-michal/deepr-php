<?php

namespace Deepr\tests\classes;

/**
 * Class Root
 * This object is provided for Deepr->invokeQuery
 * @package Deepr\tests\classes
 * @author Michal Stefanak
 * @link https://github.com/stefanak-michal/deepr-php
 */
class Root
{
    /**
     * Reference to Movies collection
     * Only public properties are accessible
     * {"movies": {...}}
     * @internal It can be instance of class Movies directly
     * @var string|Movies
     * @see \Deepr\tests\classes\Movies
     */
    public $movies = Movies::class;

    /**
     * Sample method
     * RPC methods has to be public and returns IComponent
     * {"info": {"()": [], "date": true}}
     * @see \Deepr\tests\DeeprTest::testDeepr()
     * @return array
     */
    public function info(): array
    {
        return ['date' => '2021-07-20'];
    }

    /**
     * For testing OPTION_CONTEXT
     * @see \Deepr\tests\DeeprTest::testOptionContext()
     * @param string $value
     * @param string $prefix
     * @return array
     */
    public function sayHello(string $value, string $prefix = 'Hello'): array
    {
        return ['msg' => $prefix . ' ' . $value . '!'];
    }
}
