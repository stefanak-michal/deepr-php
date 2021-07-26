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
     * Clear children collection
     */
    final public function clear()
    {
        $this->children = [];
    }

    /**
     * @inheritDoc
     */
    final public function execute(array $options = [])
    {
        $output = [];

        foreach ($this->getChildren() as $key => $child) {
            $result = $child->execute($options);

            if ($key !== '=>' && substr($key, -2) === '=>') { //unnest
                $output = $result;
            } elseif (is_array($result)) {
                if (strpos($key, '=>') === false) { //just a key
                    $output[$key] = $result;
                } else {
                    list ($k, $a) = explode('=>', $key);
                    if (empty($k) && empty($a)) { //to return
                        $output = array_merge($output, $result);
                    } elseif (!empty($a)) { //nest
                        $output = array_merge($output, [$a => $result]);
                    }
                }
            } else { //value
                $output[$key] = $result;
            }
        }

        return $output;
    }
}
