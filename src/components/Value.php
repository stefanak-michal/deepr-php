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
    /**
     * @var mixed
     */
    private $value;

    /**
     * Value constructor.
     * @param mixed $value
     */
    public function __construct($value)
    {
        $this->value = $value;
    }

    /**
     * @inheritDoc
     */
    final public function execute(array $options = [])
    {
        return $this->value;
    }
}
