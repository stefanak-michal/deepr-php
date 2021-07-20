<?php

namespace Deepr\tests\classes;

use Deepr\components\Collection;
use Deepr\components\Value;

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
     */
    public $movies = Movies::class;

    /**
     * Sample method
     * RPC methods has to be public and returns Collection
     * {"date": {"()": []}}
     * @return Collection
     */
    public function date(): Collection
    {
        $collection = new Collection();
        $collection->add(new Value('2021-07-20'));
        return $collection;
    }
}
