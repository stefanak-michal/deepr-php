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
     * Apply query on specific Collection instance
     * @param Collection $root
     * @param array $query
     * @throws Exception
     */
    public function invokeQuery(Collection $root, array $query)
    {
        foreach ($query as $key => $value) {
            if ($key === '||')
                throw new Exception('Parallel processing not implemented');

            $unnest = false;
            if ($this->isUnnest($key)) {
                $key = $this->getKey($key, false);
                $unnest = true;
            }

            if (property_exists($root, $key)) {
                $collection = $root->$key;
                if (is_string($collection) && class_exists($collection)) {
                    $collection = new $collection();
                }
            } elseif (method_exists($root, $key) && array_key_exists('()', $value)) {
                $collection = $root->{$key}(...$value['()']);
            }

            if (isset($collection) && $collection instanceof IComponent) {
                $this->recursion($collection, $key, $value);

                if ($unnest) {
                    if ($collection instanceof Collection) {
                        foreach ($collection->getChildren() as $child)
                            $root->add($child);
                    }
                } else {
                    $root->add($collection, $key);
                }
            } else {
                throw new Exception('Requested value "' . $key . '" is not valid property or method call');
            }
        }
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
                $this->recursion($root, $action, $v);
            } elseif ($k === '[]' && !empty($action)) {
                if (self::$debug)
                    var_dump($action . ' []');
                if ($root instanceof ILoadable) {
                    $offset = 0;
                    $length = null;
                    if (is_int($v)) {
                        $offset = $v;
                        $length = 1;
                    } elseif (is_array($v)) {
                        $offset = $v[0] ?? 0;
                        $length = $v[1] ?? null;
                    }

                    $tmpValues = $values;
                    unset($tmpValues['[]']);
                    foreach ($root->load($offset, $length) as $item) {
                        $this->recursion($item, $action, $tmpValues);
                        $root->add($item);
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
                    $nest = $this->isNest($k);
                    foreach ($data->getChildren() as $child) {
                        $this->recursion($child, $key, $v);
                        if (!$nest)
                            $root->add($child);
                    }

                    if ($nest)
                        $root->add($data, $this->getKey($k));
                } else {
                    throw new Exception('Method response has to be Collection');
                }
            } elseif ($v === true) {
                if (self::$debug)
                    var_dump($action . ' ' . $k . ' true');
                if (property_exists($root, $key))
                    $root->add(new Value($root->$key), $this->getKey($k));
            } elseif (is_array($v)) {
                if ($k === '=>') {
                    if (self::$debug)
                        var_dump($action . ' array return');
                    $this->recursion($root, $action, $v);
                } elseif ($this->isNest($k)) {
                    if (self::$debug)
                        var_dump($action . ' array nest');
                    $clone = clone $root;
                    $this->recursion($clone, $action, $v);
                    $root->add($clone, $this->getKey($k));
                } elseif ($this->isUnnest($k)) {
                    if (self::$debug)
                        var_dump($action . ' array unnest');
                    $this->recursion($root, $this->getKey($k, false), $v);
                }
            }
        }
    }

    /**
     * Is "=>target", "source=>target" or "key"
     * @param string $key
     * @return bool
     */
    private function isNest(string $key): bool
    {
        if (strpos($key, '=>') !== false) {
            list($a, $b) = explode('=>', $key, 2);
            return !empty($b);
        }
        return true;
    }

    /**
     * Is "source=>"
     * @param string $key
     * @return bool
     */
    private function isUnnest(string $key): bool
    {
        if (strpos($key, '=>') !== false) {
            list($a, $b) = explode('=>', $key, 2);
            return !empty($a) && empty($b);
        }
        return false;
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
