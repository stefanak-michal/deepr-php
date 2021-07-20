<?php

class Deepr
{
    public static $debug = false;

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

    private function recursion(IComponent $root, string $action, array $values)
    {
        foreach ($values as $k => $v) {
            $key = $this->getKey($k, false);

            if (is_int($k)) {
                $this->recursion($root, $action, $v);
            } elseif ($k === '[]' && !empty($action)) {
                if (self::$debug)
                    var_dump($action . ' []');
                if (!($root instanceof Loadable))
                    throw new Exception('You are trying access collection on not loadable object');

                $offset = 0;
                $length = null;
                if (is_int($v)) {
                    $offset = $v;
                    $length = 1;
                } elseif (is_array($v)) {
                    $offset = $v[0] ?? 0;
                    $length = $v[1] ?? null;
                }

                $items = $root->load();
                $tmpValues = $values;
                unset($tmpValues['[]']);
                foreach (array_slice($items, $offset, $length) as $item) {
                    $this->recursion($item, $action, $tmpValues);
                    $root->add($item);
                }

                return;
            } elseif ($k === '()') {
                continue;
            } elseif (method_exists($root, $key) && is_array($v) && array_key_exists('()', $v)) {
                if (self::$debug)
                    var_dump($key . ' ()');

                $data = $root->{$key}(...$v['()']);
                if (!($data instanceof Collection))
                    throw new Exception('Method response has to be Collection');

                $nest = $this->isNest($k);
                foreach ($data->getChildren() as $child) {
                    $this->recursion($child, $key, $v);
                    if (!$nest)
                        $root->add($child);
                }

                if ($nest)
                    $root->add($data, $this->getKey($k));
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
                }
                elseif ($this->isNest($k)) {
                    if (self::$debug)
                        var_dump($action . ' array nest');
                    $clone = clone $root;
                    $this->recursion($clone, $action, $v);
                    $root->add($clone, $this->getKey($k));
                } elseif ($this->isUnnest($k)) {
                    if (self::$debug)
                        var_dump($action . ' array unnest');
                    $this->recursion($root, $this->getKey($k, false), $v);
                } else {
                    if (self::$debug)
                        var_dump($action . ' array iterate');
                    $this->recursion($root, $k, $v);
                }
            }
        }
    }

    private function isNest(string $key): bool
    {
        if (strpos($key, '=>') !== false) {
            list($a, $b) = explode('=>', $key, 2);
            return !empty($b);
        }
        return true;
    }

    private function isUnnest(string $key): bool
    {
        if (strpos($key, '=>') !== false) {
            list($a, $b) = explode('=>', $key, 2);
            return !empty($a) && empty($b);
        }
        return false;
    }

    private function getKey(string $key, bool $alias = true): string
    {
        if (strpos($key, '=>') === false)
            return $key;

        list($k, $a) = explode('=>', $key, 2);
        return $alias ? ($a ?? $k) : $k;
    }

    private function getAlias(string $key): ?string
    {
        if (strpos($key, '=>') === false)
            return null;

        return explode('=>', $key, 2)[1] ?? null;
    }
}

/*
 * @link https://refactoring.guru/design-patterns/composite
 */

interface IComponent
{
    public function execute();
}

class Collection implements IComponent
{
    private $children = [];

    public function add(IComponent $c, string $name = '')
    {
        if (!empty($name))
            $this->children[$name] = $c;
        else
            $this->children[] = $c;
    }

    public function remove(IComponent $c)
    {
        $key = array_search($c, $this->children);
        if ($key !== false)
            unset($this->children[$key]);
    }

    /**
     * @return IComponent[]
     */
    public function getChildren(): array
    {
        return $this->children;
    }

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

class Value implements IComponent
{
    private $value;

    public function __construct($value)
    {
        $this->value = $value;
    }

    public function execute()
    {
        return $this->value;
    }
}

interface Loadable
{
    public function load(): array;
}
