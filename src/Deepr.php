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
     * Source Values argument key
     */
    const OPTION_SV_KEY = 1;
    /**
     * Source values class names namespace prefix
     */
    const OPTION_SV_NS = 2;

    /**
     * Enable to see query traversal
     * @var bool
     */
    public static $debug = false;

    /**
     * Default options
     * @var array
     */
    private static $defaultOptions = [
        self::OPTION_SV_KEY => '_type',
        self::OPTION_SV_NS => ''
    ];

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

        if (key($query) === '||')
            throw new Exception('Parallel processing not implemented');

        $this->recursion($root, key($query), $query);
        return $root->execute($this->options);
    }

    /**
     * Create instance of structure class by parameters
     * @link https://github.com/deeprjs/deepr#source-values
     * @param array $args
     * @return IComponent
     * @throws Exception
     */
    private function getInstance(array $args): IComponent
    {
        if (!array_key_exists($this->options[self::OPTION_SV_KEY], $args))
            throw new Exception('Source values type key not found in arguments');

        $cls = $this->options[self::OPTION_SV_NS] . $args[$this->options[self::OPTION_SV_KEY]];
        if (!class_exists($cls))
            throw new Exception('Requested class "' . $cls . '" does not exists');

        $reflection = new \ReflectionClass($cls);
        $invokeArgs = [];
        if ($reflection->getConstructor()) {
            foreach ($reflection->getConstructor()->getParameters() as $parameter) {
                if (array_key_exists($parameter->getName(), $args))
                    $invokeArgs[] = $args[$parameter->getName()];
            }
        }

        $instance = new $cls(...$invokeArgs);
        if (!($instance instanceof IComponent))
            throw new Exception($cls . ' has to implement IComponent');

        return $instance;
    }

    /**
     * @param IComponent $root
     * @param string $action
     * @param array $values
     * @throws Exception
     */
    private function recursion(IComponent &$root, string $action, array $values)
    {
        foreach ($values as $k => $v) {
            $key = $this->getKey($k, false);

            if ($k === '<=') {
                if (self::$debug)
                    var_dump('<=');
                $tmpValues = $values;
                unset($tmpValues['<=']);
                $instance = $this->getInstance($v);
                $root = $instance;
            } elseif (is_int($k)) {
                if (self::$debug)
                    var_dump('array');
                $clone = clone $root;
                $clone->clear();
                $this->recursion($clone, $action, $v);
                $root->add($clone);
            } elseif ($k === '[]' && !empty($action)) {
                if (self::$debug)
                    var_dump($action . ' []');
                if (!($root instanceof ILoadable))
                    throw new Exception('To access collection of class it has to implement ILoadable interface');

                $tmpValues = $values;
                unset($tmpValues['[]']);

                if (is_int($v)) {
                    foreach ($root->load($v, 1)->getChildren() as $child) {
                        $this->recursion($child, $action, $tmpValues);
                        $root = $child;
                    }
                } elseif (is_array($v)) {
                    foreach ($root->load($v[0] ?? 0, $v[1] ?? null)->getChildren() as $name => $child) {
                        $this->recursion($child, $action, $tmpValues);
                        $root->add($child, $name);
                    }
                }
            } elseif ($k === '()') {
                continue;
            } elseif (is_array($v) && array_key_exists('()', $v)) {
                if (self::$debug)
                    var_dump($key . ' ()');

                if (method_exists($root, $key)) {
                    $data = $root->{$key}(...$v['()']);
                    if ($root === $data) {
                        $root = new Collection();
                    }
                    $this->recursion($data, $key, $v);
                    if ($data instanceof Collection) {
                        foreach ($data->getChildren() as $child) {
                            $this->recursion($child, '', $v);
                        }
                    }
                    $root->add($data, $k);
                } elseif ($root instanceof Collection) {
                    foreach ($root->getChildren() as $child) {
                        $this->recursion($child, $k, $v);
                    }
                }
            } elseif ($v === true) {
                if (self::$debug)
                    var_dump($action . ' ' . $k . ' true');
                if (property_exists($root, $key)) {
                    $root->add(new Value($root->$key), $k);
                }
            } elseif (property_exists($root, $key)) {
                if (self::$debug)
                    var_dump('property ' . $key);
                $collection = $root->$key;
                if (is_string($collection) && class_exists($collection)) {
                    $collection = new $collection();
                }
                $this->recursion($collection, $key, $v);
                $root->add($collection, $k);
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
