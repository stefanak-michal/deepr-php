<?php

namespace Deepr\tests\classes;

use Deepr\components\{Collection, Value, IComponent};

/**
 * Class Root
 * This object is provided for Deepr->invokeQuery
 * @package Deepr\tests\classes
 * @author Michal Stefanak
 * @link https://github.com/stefanak-michal/deepr-php
 */
class Root extends Collection
{
    /**
     * Reference to Movies collection
     * Only public properties are accessible
     * {"movies": ...}
     * @internal It can be instance of class Movies directly
     * @var string|Collection
     * @see \Deepr\tests\classes\Movies
     */
    public $movies = Movies::class;

    /**
     * Sample method
     * RPC methods has to be public and returns IComponent
     * {"date": {"()": []}}
     * @return IComponent
     */
    public function date(): IComponent
    {
        return new Value('2021-07-20');
    }

    /**
     * @param string $value
     * @param string $prefix
     * @return IComponent
     */
    public function sayHello(string $value, string $prefix = 'Hello'): IComponent
    {
        return new Value($prefix . ' ' . $value . '!');
    }
}
