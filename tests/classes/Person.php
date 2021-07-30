<?php

namespace Deepr\tests\classes;

use Deepr\components\Collection;

/**
 * Class Person
 * @package Deepr\tests\classes
 * @author Michal Stefanak
 * @link https://github.com/stefanak-michal/deepr-php
 */
class Person extends Collection
{
    public $_id;
    /**
     * {"movies":{"[]":[],"=>":{"getActors":{"()":[], "name": true}}}}
     * @var string
     */
    public $name;
    public $born;

    public $_movies;

    public function getMovies()
    {
        $items = new Collection();
        if (empty($this->_movies))
            return $items;
        foreach (Database::getMovies() as $row) {
            if (in_array($row['_id'], $this->_movies)) {
                $movie = new Movie();
                $movie->_id = $row['_id'];
                $movie->title = $row['title'];
                $movie->released = $row['released'];
                $movie->tagline = $row['tagline'];
                $items->add($movie);
            }
        }
        return $items;
    }
}
