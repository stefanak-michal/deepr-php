<?php

namespace Deepr\components;

/**
 * Interface ILoadable
 * @package Deepr\components
 * @author Michal Stefanak
 * @link https://github.com/stefanak-michal/deepr-php
 */
interface ILoadable
{
    /**
     * Get collection on "[]" call
     * @param int $offset
     * @param int|null $length
     * @return Collection
     */
    public function load(int $offset, ?int $length): Collection;
}
