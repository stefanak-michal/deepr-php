<?php

namespace Deepr\components;

/**
 * Class Collection
 * @package Deepr\components
 * @author Michal Stefanak
 * @link https://github.com/stefanak-michal/deepr-php
 */
class Collection implements IComponent
{
    /**
     * @var IComponent[]
     */
    private $children = [];

    /**
     * Add child to collection
     * @param IComponent $component
     * @param string $name [Optional] To store under specific key in collection
     */
    final public function add(IComponent $component, string $name = '')
    {
        if (!empty($name))
            $this->children[$name] = $component;
        else
            $this->children[] = $component;
    }

    /**
     * Get list of children
     * @return IComponent[]
     */
    final public function getChildren(): array
    {
        return $this->children;
    }

    /**
     * @inheritDoc
     */
    final public function execute(array $options = [])
    {
        $output = [];

        foreach ($this->getChildren() as $key => $child) {
            $output = array_merge($output, [$key => $child->execute($options)]);
        }

        if ($options[\Deepr\Deepr::OPTION_UNNEST_ONE_CHILD] && count($output) == 1 && is_int(key($output))) {
            $output = reset($output);
        }

        return $output;
    }
}
