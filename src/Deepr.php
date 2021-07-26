<?php

namespace Deepr;

use Deepr\components\{
    IComponent,
    Collection,
    ILoadable,
    Value
};
use \Exception;

/**
 * Class Deepr
 *
 * @package Deepr
 * @author Michal Stefanak
 * @link https://github.com/stefanak-michal/deepr-php
 * @link https://refactoring.guru/design-patterns/composite
 */
final class Deepr
{
    /**
     * Enable to see query traversal
     * @var bool
     */
    public static $debug = false;

    /**
     * Default options
     * @var array
     */
    private static $defaultOptions = [];

    /**
     * Current options
     * @var array
     */
    private $options;

    /**
     * Apply query on specific Collection instance
     * @param Collection $root
     * @param array $query
     * @param array $options
     * @return array
     * @throws Exception
     */
    public function invokeQuery(Collection $root, array $query, array $options = []): array
    {
        $this->options = array_replace(self::$defaultOptions, array_intersect_key($options, self::$defaultOptions));

        if (reset($query) === '||')
            throw new Exception('Parallel processing not implemented');

        if (array_filter(array_keys($query), 'is_int') == array_keys($query)) {
            $clone = clone $root;
            foreach ($query as $key => $value) {
                $clone->clear();
                $this->invokeQuery($clone, $value, $options);
                $root->add($clone);
            }
            return $root->execute($options);
        }

        foreach ($query as $key => $value) {
            $action = $this->getKey($key, false);

            if (property_exists($root, $action)) {
                $collection = $root->$action;
                if (is_string($collection) && class_exists($collection)) {
                    $collection = new $collection();
                }
            } elseif (method_exists($root, $action) && array_key_exists('()', $value)) {
                $collection = $root->{$action}(...$value['()']);
            }

            if (isset($collection) && $collection instanceof IComponent) {
                $this->recursion($collection, $action, $value);
                $root->add($collection, $key);
            } else {
                throw new Exception('Requested value "' . $key . '" is not valid property or method call');
            }
        }

        return $root->execute($this->options);
    }

    /**
     * @param IComponent $root
     * @param string $action
     * @param array $values
     * @throws Exception
     */
    private function recursion(IComponent $root, string $action, array $values)
    {
        foreach ($values as $k => $v) {
            $key = $this->getKey($k, false);

            if (is_int($k)) {
                $clone = clone $root;
                $this->recursion($clone, $action, $v);
                $root->add($clone);
            } elseif ($k === '[]' && !empty($action)) {
                if (self::$debug)
                    var_dump($action . ' []');
                if ($root instanceof ILoadable) {
                    $tmpValues = $values;
                    unset($tmpValues['[]']);

                    if (is_int($v)) {
                        foreach ($root->load($v, 1)->getChildren() as $child) {
                            $this->recursion($child, $action, $tmpValues);
                            if ($child instanceof Collection)
                                foreach ($child->getChildren() as $name => $ch) {
                                    $root->add($ch, $name);
                                }
                        }
                    } elseif (is_array($v)) {
                        foreach ($root->load($v[0] ?? 0, $v[1] ?? null)->getChildren() as $child) {
                            $this->recursion($child, $action, $tmpValues);
                            $root->add($child);
                        }
                    }

                } else {
                    throw new Exception('To access collection of class it has to implement ILoadable interface');
                }
                return;
            } elseif ($k === '()') {
                continue;
            } elseif (method_exists($root, $key) && is_array($v) && array_key_exists('()', $v)) {
                if (self::$debug)
                    var_dump($key . ' ()');

                $data = $root->{$key}(...$v['()']);
                if ($data instanceof Collection) {
                    $this->recursion($data, $key, $v);
                    foreach ($data->getChildren() as $child) {
                        $this->recursion($child, $key, $v);
                    }

                    $root->add($data, $k);
                } else {
                    throw new Exception('Method response has to be Collection');
                }
            } elseif ($v === true) {
                if (self::$debug)
                    var_dump($action . ' ' . $k . ' true');
                if (property_exists($root, $key)) {
                    $root->add(new Value($root->$key), $k);
                }
            } elseif (is_array($v)) {
                if (self::$debug)
                    var_dump($action . ' array nest');
                $clone = clone $root;
                $this->recursion($clone, $action, $v);
                $root->add($clone, $k);
            }
        }
    }

    /**
     * Get final key
     * @param string $key
     * @param bool $alias If you want the source key set this to false, otherwise will return target key
     * @return string
     */
    private function getKey(string $key, bool $alias = true): string
    {
        if (strpos($key, '=>') === false)
            return $key;

        list($k, $a) = explode('=>', $key, 2);
        return $alias ? ($a ?? $k) : $k;
    }

}
