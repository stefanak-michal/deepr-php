<?php

namespace Deepr\tests\classes;

/**
 * Class Person
 * @package Deepr\tests\classes
 * @author Michal Stefanak
 * @link https://github.com/stefanak-michal/deepr-php
 */
class Person
{
    public $_id;
    /**
     * {"movies":{"[]":[],"=>":{"getActors":{"()":[], "name": true}}}}
     * @var string
     */
    public $name;
    /**
     * @var int
     */
    public $born;

    /**
     * IDs of movies
     * @var array
     */
    public $_movies;

    /**
     * Person constructor. Use source values call to use it.
     * {"<=": {"_type": "Person","id": 1}, ..}
     * @param int $id
     */
    public function __construct(int $id = -1)
    {
        if ($id != -1) {
            $row = Database::getActor($id);
            if (!empty($row)) {
                $this->_id = $row['_id'];
                $this->name = $row['name'];
                $this->born = $row['born'];
                $this->_movies = $row['_movies'];
            }
        }
    }

    /**
     * RPC method to get movies in which actor acted
     * {..actor: {"getMovies":{"()": []}}
     * @return array
     */
    public function getMovies(): array
    {
        $items = [];
        if (empty($this->_movies))
            return $items;
        foreach (Database::getMovies() as $row) {
            if (in_array($row['_id'], $this->_movies)) {
                $movie = new Movie();
                $movie->_id = $row['_id'];
                $movie->title = $row['title'];
                $movie->released = $row['released'];
                $movie->tagline = $row['tagline'];
                $items[] = $movie;
            }
        }
        return $items;
    }
}
