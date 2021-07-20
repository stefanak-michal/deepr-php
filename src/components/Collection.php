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
    public function add(IComponent $component, string $name = '')
    {
        if (!empty($name))
            $this->children[$name] = $component;
        else
            $this->children[] = $component;
    }

    /**
     * Remove child from collection
     * @param IComponent $component
     */
    public function remove(IComponent $component)
    {
        $key = array_search($component, $this->children);
        if ($key !== false)
            unset($this->children[$key]);
    }

    /**
     * Get list of children
     * @return IComponent[]
     */
    public function getChildren(): array
    {
        return $this->children;
    }

    /**
     * @inheritDoc
     */
    public function execute()
    {
        $output = [];

        foreach ($this->getChildren() as $key => $child) {
            $output = array_merge($output, [$key => $child->execute()]);
        }

        if (count($output) == 1 && is_int(key($output))) {
            $output = reset($output);
        }

        return $output;
    }
}
