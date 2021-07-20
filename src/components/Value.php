<?php

namespace Deepr\components;

/**
 * Class Value
 * @package Deepr\components
 * @author Michal Stefanak
 * @link https://github.com/stefanak-michal/deepr-php
 */
class Value implements IComponent
{
    private $value;

    public function __construct($value)
    {
        $this->value = $value;
    }

    public function execute()
    {
        return $this->value;
    }
}
