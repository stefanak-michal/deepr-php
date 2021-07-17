<?php

class Deepr
{
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

            if (isset($collection) && $collection instanceof Collection) {
                $this->recursion($collection, $key, $value);

                if ($unnest) {
                    foreach ($collection->getChildren() as $child)
                        $root->add($child);
                } else {
                    $root->add($collection, $key);
                }
            } else {
                throw new Exception('Requested value "' . $key . '" is not valid property or method call');
            }
        }
    }

    private function recursion(Collection $root, string $action, array $values): void
    {
        foreach ($values as $k => $v) {
            if (is_int($k)) {
                $this->recursion($root, $action, $v);
            } elseif ($k === '[]') {
                $offset = 0;
                $length = null;
                if (is_int($v)) {
                    $offset = $v;
                    $length = 1;
                } elseif (is_array($v)) {
                    $offset = $v[0] ?? 0;
                    $length = $v[1] ?? null;
                }

                $root->load();
                $children = $root->getChildren();
                $root->clear();
                foreach (array_slice($children, $offset, $length) as $row)
                    $root->add($row);
            } elseif ($k === '()') {
                if (!method_exists($root, $action))
                    throw new Exception('Missing method "' . $action . '" on "' . get_class($root) . '"');

                $data = $root->{$action}(...$v);

                if ($data instanceof Collection) {
                    foreach ($data->getChildren() as $child) {
                        if ($child instanceof IComponent)
                            $root->add($child);
                    }
                } elseif ($data instanceof IComponent) {
                    $root->add($data);
                }
            } elseif ($k === 'count' && $v === true) {
                $root->requestColumn($k, $root->count());
            } elseif ($v === true) {
                $key = $this->getKey($k, false);
                $unnest = $this->isUnnest($k);
                $children = $root->getChildren();
                if ($unnest)
                    $root->clear();

                foreach ($children as $child) {
                    if ($child instanceof AComponent) {
                        if (property_exists($child, $key)) {
                            if ($unnest) {
                                $root->add(new Value($child->$key));
                            } else {
                                $child->requestColumn($key, $this->getAlias($k));
                            }
                        }
                    }
                }
            } elseif (is_array($v)) {
                if ($this->isNest($k)) {
                    $cls = get_class($root);
                    $clone = new $cls();
                    $this->recursion($clone, $this->getKey($k, false), $v);
                    $root->add($clone, $this->getKey($k));
                } elseif ($this->isUnnest($k)) {
                    $this->recursion($root, $this->getKey($k, false), $v);
                }
            }
        }
    }

    private function isNest(string $key): bool
    {
        return strpos($key, '=>') !== false && !$this->isUnnest($key);
    }

    private function isUnnest(string $key): bool
    {
        return substr($key, -2) == '=>';
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

trait ComponentColumns
{
    private $columns = [];

    public function requestColumn(string $column, $value = null)
    {
        if (is_null($value)) {
            if (!in_array($column, $this->columns))
                $this->columns[] = $column;
        } else {
            $this->columns[$column] = $value;
        }
    }

    protected function getColumns(): array
    {
        return $this->columns;
    }
}

abstract class AComponent implements IComponent
{
    use ComponentColumns;

    public function execute(): array
    {
        $output = [];
        foreach ($this->getColumns() as $a => $b) {
            $column = is_int($a) ? $b : $a;
            if (property_exists($this, $column))
                $output[$b] = $this->$column;
        }
        return $output;
    }
}

class Collection implements IComponent
{
    use ComponentColumns;

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

    public function clear()
    {
        $this->children = [];
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
        $output = $this->getColumns();

        foreach ($this->getChildren() as $key => $child) {
            $output = array_merge($output, [$key => $child->execute()]);
        }

        if (count($output) == 1 && is_int(key($output))) {
            $output = reset($output);
        }

        return $output;
    }

    public function count(): int
    {
        return count($this->children);
    }

    public function load()
    {

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
