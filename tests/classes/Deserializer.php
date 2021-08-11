<?php

namespace Deepr\tests\classes;

use Exception;

/**
 * Class Deserializer
 * @package Deepr\tests\classes
 * @author Michal Stefanak
 * @link https://github.com/stefanak-michal/deepr-php
 */
class Deserializer
{
    /**
     * @param string|array $query
     * @return array
     * @throws Exception
     */
    public function deserialize($query): array
    {
        if (is_string($query)) {
            $query = json_decode($query, true);
            if (json_last_error() != JSON_ERROR_NONE)
                throw new Exception(json_last_error_msg());
        } elseif (!is_array($query)) {
            throw new Exception('Deserialization expects JSON string or array');
        }

        return $this->recursion($query);
    }

    /**
     * @param array $query
     * @return array
     * @throws Exception
     */
    private function recursion(array $query): array
    {
        foreach ($query as $key => &$value) {
            if ($key === '<=') { // insert class instance by query
                if (array_key_exists('_type', $value) && class_exists("\\Deepr\\tests\\classes\\" . $value['_type'])) {
                    $clsName = "\\Deepr\\tests\\classes\\" . $value['_type'];
                    unset($value['_type']);
                    $value = new $clsName(...array_values($value));
                }
            }

            if (is_array($value)) {
                $value = $this->deserialize($value);
            }
        }

        return $query;
    }
}
