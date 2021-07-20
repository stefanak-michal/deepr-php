<?php

namespace Deepr\tests\classes;

use Deepr\components\Collection;

/**
 * Class Person
 * @package Deepr\tests\classes
 * @author Michal Stefanak
 * @link https://github.com/stefanak-michal/deepr-php
 */
class Person extends Collection
{
    public $_id;
    /**
     * {"movies":{"[]":[],"=>":{"getActors":{"()":[], "name": true}}}}
     * @var string
     */
    public $name;
    public $born;
}
