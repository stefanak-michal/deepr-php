<?php

namespace Deepr;

use Deepr\components\{
    IComponent,
    Collection,
    ILoadable,
    Value
};
use \Exception;
use \ReflectionClass;

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
     * @param string
     */
    const OPTION_SV_KEY = 1;
    /**
     * Source values class names namespace prefix
     * @param string
     */
    const OPTION_SV_NS = 2;
    /**
     * A context that will be passed as the last parameter to all invoked methods.
     * `null` value is ignored and it's not passed as argument
     * @param mixed
     */
    const OPTION_CONTEXT = 3;
    /**
     * A key or an array of keys to be ignored when executing the query. A key can be specified as a string or a RegExp.
     * @param array
     */
    const OPTION_IGNORE_KEYS = 4;
    /**
     * A key or an array of keys to be accepted regardless if they are ignored using the ignoreKeys option. A key can be specified as a string or a RegExp.
     * @param array
     */
    const OPTION_ACCEPT_KEYS = 5;
    /**
     * A function that is called for each key to authorize any operation.
     * The function receives a key and an operation which can be either 'get' for reading an attribute or 'call' for invoking a method.
     * The function must return true to authorize an operation. If false is returned, the evaluation of the query stops immediately, and an error is thrown.
     * @param callable
     */
    const OPTION_AUTHORIZER = 6;

    /**
     * Authorizer
     */
    const ERROR_AUTHORIZER = 1;
    /**
     * Set options
     */
    const ERROR_OPTIONS = 2;
    /**
     * Get class instance
     */
    const ERROR_INSTANCE = 3;
    /**
     * Wrong structure of classes
     */
    const ERROR_STRUCTURE = 4;
    /**
     * Missing property or method
     */
    const ERROR_MISSING = 5;

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
        self::OPTION_SV_NS => '',
        self::OPTION_CONTEXT => null,
        self::OPTION_IGNORE_KEYS => [],
        self::OPTION_ACCEPT_KEYS => [],
        self::OPTION_AUTHORIZER => null
    ];

    /**
     * Current options
     * @var array
     */
    private $options;

    /**
     * Apply query on specific Collection instance
     * @see setOptions()
     * @param Collection $root
     * @param array $query
     * @param array $options
     * @return array
     * @throws Exception
     */
    public function invokeQuery(Collection $root, array $query, array $options = []): array
    {
        $this->setOptions($options);

        if (key($query) === '||')
            throw new Exception('Parallel processing not implemented');

        $this->recursion($root, key($query), $query);
        return $root->execute($this->options);
    }

    /**
     * @link https://github.com/stefanak-michal/deepr-php/wiki
     * @param array $options
     * @return $this
     * @throws Exception
     */
    public function setOptions(array $options = []): Deepr
    {
        $this->options = array_replace(self::$defaultOptions, array_intersect_key($options, self::$defaultOptions));

        $reflection = new ReflectionClass($this);
        foreach ($reflection->getReflectionConstants() as $constant) {
            if (preg_match("/@param ([a-z]+)/", $constant->getDocComment(), $match)) {
                switch ($match[1]) {
                    case 'string':
                        if (!is_string($this->options[$constant->getValue()])) {
                            if (empty($this->options[$constant->getValue()]))
                                $this->options[$constant->getValue()] = '';
                            else
                                throw new Exception($constant->getName() . ' accept value of type string', self::ERROR_OPTIONS);
                        }
                        break;
                    case 'mixed':
                        break;
                    case 'array':
                        if (!is_array($this->options[$constant->getValue()])) {
                            if (empty($this->options[$constant->getValue()]))
                                $this->options[$constant->getValue()] = [];
                            else
                                throw new Exception($constant->getName() . ' accept value of type array', self::ERROR_OPTIONS);
                        }
                        break;
                    case 'callable':
                        if (!is_callable($this->options[$constant->getValue()])) {
                            if (empty($this->options[$constant->getValue()]))
                                $this->options[$constant->getValue()] = null;
                            else
                                throw new Exception($constant->getName() . ' accept value of type callable or null', self::ERROR_OPTIONS);
                        }
                        break;
                }
            }
        }

        return $this;
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
            throw new Exception('Source values type key not found in arguments', self::ERROR_INSTANCE);

        $cls = $this->options[self::OPTION_SV_NS] . $args[$this->options[self::OPTION_SV_KEY]];
        if (!class_exists($cls))
            throw new Exception('Requested class "' . $cls . '" does not exists', self::ERROR_INSTANCE);

        $reflection = new ReflectionClass($cls);
        $invokeArgs = [];
        if ($reflection->getConstructor()) {
            foreach ($reflection->getConstructor()->getParameters() as $parameter) {
                if (array_key_exists($parameter->getName(), $args))
                    $invokeArgs[] = $args[$parameter->getName()];
            }
        }

        $instance = new $cls(...$invokeArgs);
        if (!($instance instanceof IComponent))
            throw new Exception($cls . ' has to implement IComponent', self::ERROR_STRUCTURE);

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
                $this->debug('<=');
                $tmpValues = $values;
                unset($tmpValues['<=']);
                $instance = $this->getInstance($v);
                $root = $instance;
            } elseif (is_int($k)) {
                $this->debug('array');
                $clone = clone $root;
                $clone->clear();
                $this->recursion($clone, $action, $v);
                $root->add($clone);
            } elseif ($k === '[]' && !empty($action)) {
                $this->debug($action . ' []');
                if (!($root instanceof ILoadable))
                    throw new Exception('To access collection of class it has to implement ILoadable interface', self::ERROR_STRUCTURE);

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
                return;
            } elseif ($k === '()') {
                continue;
            } elseif (is_array($v) && array_key_exists('()', $v)) {
                $this->debug($key . ' ()');
                $this->authorize($key, 'call');

                if (!is_null($this->options[self::OPTION_CONTEXT]))
                    $v['()'][] = $this->options[self::OPTION_CONTEXT];

                if (method_exists($root, $key)) {
                    $data = $root->{$key}(...$v['()']);

                    if (is_null($data)) {
                        $this->recursion($root, $key, $v);
                        $collection = new Collection();
                        $collection->add($root, $k);
                        $root = $collection;
                        return;
                    }

                    if ($root === $data)
                        $root = new Collection();
                    if (is_subclass_of($data, Collection::class))
                        $this->recursion($data, '', $v);
                    elseif ($data instanceof Collection) {
                        foreach ($data->getChildren() as $child)
                            $this->recursion($child, '', $v);
                    }
                    if ($data instanceof IComponent)
                        $root->add($data, $k);
                    else
                        throw new Exception('Method has to return instance of IComponent or null', self::ERROR_STRUCTURE);
                } elseif (strpos($k, '?') === false) {
                    throw new Exception('Missing method ' . $key, self::ERROR_MISSING);
                }
            } elseif ($v === true) {
                $this->debug($action . ' ' . $k . ' true');
                if (property_exists($root, $key)) {
                    if ($this->checkPropertyKey($key)) {
                        $this->authorize($key);
                        $root->add(new Value($root->$key), $k);
                    }
                } elseif (strpos($k, '?') === false) {
                    throw new Exception('Missing property ' . $key, self::ERROR_MISSING);
                }
            } elseif (property_exists($root, $key)) {
                $this->debug('property ' . $key);
                $collection = $root->$key;
                if (is_string($collection) && class_exists($collection))
                    $collection = new $collection();
                if (!($collection instanceof Collection))
                    throw new Exception('Property has to be instance of collection class or class name', self::ERROR_STRUCTURE);
                $this->recursion($collection, $key, $v);
                $root->add($collection, $k);
            } elseif (is_array($v)) {
                $this->debug($action . ' array nest');
                $clone = clone $root;
                $this->recursion($clone, $action, $v);
                $root->add($clone, $k);
            }
        }
    }

    /**
     * Verify if key should be ignored or accepted
     * @param string $key
     * @return bool
     */
    private function checkPropertyKey(string $key): bool
    {
        foreach ($this->options[self::OPTION_ACCEPT_KEYS] as $acceptKey) {
            if ($acceptKey[0] == '/' && preg_match($acceptKey, $key) || $key === $acceptKey)
                return true;
        }

        foreach ($this->options[self::OPTION_IGNORE_KEYS] as $ignoreKey) {
            if ($ignoreKey[0] == '/' && preg_match($ignoreKey, $key) || $key === $ignoreKey)
                return false;
        }

        return true;
    }

    /**
     * @param string $key
     * @param string $operation
     * @throws Exception
     */
    private function authorize(string $key, string $operation = 'get')
    {
        if (is_callable($this->options[self::OPTION_AUTHORIZER]) && $this->options[self::OPTION_AUTHORIZER]($key, $operation) === false) {
            throw new Exception('Operation not allowed by authorizer', self::ERROR_AUTHORIZER);
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
        $key = str_replace('?', '', $key);
        if (strpos($key, '=>') === false)
            return $key;

        list($k, $a) = explode('=>', $key, 2);
        return $alias ? ($a ?? $k) : $k;
    }

    /**
     * @param string $msg
     */
    private function debug(string $msg)
    {
        if (self::$debug)
            var_dump($msg);
    }

}
