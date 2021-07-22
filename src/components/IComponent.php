<?php

namespace Deepr\components;

/**
 * Interface IComponent
 * @package Deepr\components
 * @author Michal Stefanak
 * @link https://github.com/stefanak-michal/deepr-php
 */
interface IComponent
{
    /**
     * Execute logic on component
     * @param array $options
     * @return mixed
     */
    public function execute(array $options = []);
}
