<?php

namespace Deepr;

use Exception;
use Generator;
use ReflectionClass;

/**
 * Class Deepr
 *
 * @package Deepr
 * @author Michal Stefanak
 * @link https://github.com/stefanak-michal/deepr-php
 */
final class Deepr
{
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
     * Apply query on specific object instance
     * @param object $root
     * @param array $query
     * @param array $options
     * @return array
     * @throws Exception
     * @see setOptions()
     */
    public function invokeQuery(object $root, array $query, array $options = []): array
    {
        $this->setOptions($options);

        if (key($query) === '||')
            throw new Exception('Parallel processing not implemented');

        $output = $this->iterate($root, $query);
        return iterator_to_array($output);
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
     * Iterate through query
     * @param mixed $root
     * @param array $query
     * @return Generator
     * @throws Exception
     */
    public function iterate($root, array $query): Generator
    {
        foreach ($query as $key => $value) {
            $key2 = strpos($key, '=>') > 0
                ? substr($key, 0, strpos($key, '=>'))
                : $key;

            if ($key === '<=') {
                $this->debug('<=');
                if (is_object($value)) {
                    $root = $value;
                } else {
                    throw new Exception('Key "<=" has to contains class instance. Are you using proper deserializer?', self::ERROR_INSTANCE);
                }
            } elseif ($key === '=>' && is_array($value)) {
                $this->debug('=>');
                yield from $this->iterate($root, $value);
            } elseif ($value === true) {
                $this->debug('property ' . $key);
                if ($this->checkPropertyKey($key2)) {
                    if (is_object($root) && property_exists($root, $key2)) {
                        $this->authorize($root, $key2);
                        yield $key => $root->$key2;
                    } elseif (is_array($root) && array_key_exists($key2, $root)) {
                        $this->authorize($root, $key2);
                        yield $key => $root[$key2];
                    } elseif ($key[-1] != '?') {
                        throw new Exception('Property access is available only for class instance or array.', self::ERROR_STRUCTURE);
                    }
                }
            } elseif (is_array($value) && array_key_exists('()', $value)) {
                $this->debug('method ' . $key);
                if (is_object($root) && method_exists($root, $key2)) {
                    $this->authorize($root, $key2, 'call');
                    $tmp = $value;
                    unset($tmp['()']);
                    $result = $this->invokeMethod($root, $key2, $value['()']);
                    return yield $key => iterator_to_array($this->iterate($result, $tmp));
                } elseif ($key[-1] != '?') {
                    throw new Exception('You are trying access not existing method.', self::ERROR_MISSING);
                }
            } elseif (is_array($value) && is_object($root) && property_exists($root, $key2)) {
                $this->debug('property cls ' . $key);
                if (is_string($root->$key2) && class_exists($root->$key2))
                    $root->$key2 = new $root->$key2();
                yield $key => iterator_to_array($this->iterate($root->$key2, $value));
            } elseif ($key === '[]') {
                $this->debug('array access');
                if (is_int($value)) {
                    $offset = $value;
                    $length = 1;
                } elseif (is_array($value)) {
                    $offset = $value[0] ?? 0;
                    $length = $value[1] ?? null;
                } else {
                    throw new Exception('Wrong arguments for array access.', self::ERROR_STRUCTURE);
                }

                $tmp = $query;
                unset($tmp['[]']);

                if (is_array($root))
                    $items = array_slice($root, $offset, $length);
                elseif (is_object($root) && is_callable($root))
                    $items = $root($offset, $length);
                else
                    throw new Exception('Array access is available only for array or class implementing Arrayable interface', self::ERROR_STRUCTURE);

                foreach ($items as $item) {
                    if (is_int($value))
                        yield from $this->iterate($item, $tmp);
                    else
                        yield iterator_to_array($this->iterate($item, $tmp));
                }
                return null;
            } elseif (is_array($value)) {
                $this->debug('array values');
                yield $key => iterator_to_array($this->iterate($root, $value));
            }
        }
    }

    /**
     * @param object $root
     * @param string $method
     * @param array $args
     * @return mixed
     * @throws Exception
     */
    private function invokeMethod(object $root, string $method, array $args)
    {
        if (!is_null($this->options[self::OPTION_CONTEXT]))
            $args[] = $this->options[self::OPTION_CONTEXT];

        if (count(array_filter(array_keys($args), 'is_int')) == count($args)) {
            return $root->{$method}(...$args);
        } else {
            $refMethod = (new ReflectionClass($root))->getMethod($method);
            $invokeArgs = [];
            foreach ($refMethod->getParameters() as $parameter) {
                $invokeArgs[] = array_key_exists($parameter->getName(), $args)
                    ? $args[$parameter->getName()]
                    : $parameter->getDefaultValue();
            }
            return $refMethod->invokeArgs($root, $invokeArgs);
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
     * @param array|object $root
     * @param string $key
     * @param string $operation
     * @throws Exception
     */
    private function authorize($root, string $key, string $operation = 'get')
    {
        if (is_callable($this->options[self::OPTION_AUTHORIZER]) && $this->options[self::OPTION_AUTHORIZER]($root, $key, $operation) === false) {
            throw new Exception('Operation not allowed by authorizer', self::ERROR_AUTHORIZER);
        }
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
