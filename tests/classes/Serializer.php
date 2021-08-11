<?php

namespace Deepr\tests\classes;

use Exception;

/**
 * Class Serializer
 * @package Deepr\tests\classes
 * @author Michal Stefanak
 * @link https://github.com/stefanak-michal/deepr-php
 */
class Serializer
{

    /**
     * Serialize into formatted JSON
     * @param array $result
     * @return string
     * @throws Exception
     */
    public function serialize(array $result): string
    {
        $output = $this->recursion($result);
        if (!is_string($output)) {
            $output = json_encode($output, JSON_PRETTY_PRINT);
            if (json_last_error() != JSON_ERROR_NONE)
                throw new Exception(json_last_error_msg());
        }
        return $output;
    }

    /**
     * @param array $result
     * @return mixed
     */
    private function recursion(array $result)
    {
        $output = [];
        foreach ($result as $key => $value) {
            if (is_array($value))
                $value = $this->recursion($value);

            if ($key !== '=>' && substr($key, -2) === '=>') { //unnest
                return $value;
            } else { //value
                $a = $key;
                if (strpos($key, '=>') !== false)
                    list ($k, $a) = explode('=>', $key);
                $output[$a] = $value;
            }
        }
        return $output;
    }
}
