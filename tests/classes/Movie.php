<?php

namespace Deepr\tests\classes;

use Deepr\components\Collection;
use Deepr\components\IComponent;

/**
 * Class Movie
 * @package Deepr\tests\classes
 * @author Michal Stefanak
 * @link https://github.com/stefanak-michal/deepr-php
 */
class Movie extends Collection
{
    public $_id;
    /**
     * {"movies": {"[]":[], "title": true}}
     * @var string
     */
    public $title;
    /**
     * {"movies": {"[]":[], "released": true}}
     * @var int
     */
    public $released;
    public $tagline;

    /**
     * @var Collection
     */
    private $actors;

    /**
     * RPC method to get actors of movie
     * {"movies":{"[]":[],"=>":{"getActors":{"()":[]}}}}
     * @return IComponent
     * @see \Deepr\tests\classes\Person
     */
    public function getActors(): IComponent
    {
        if (is_null($this->actors)) {
            $this->actors = new Collection();
            foreach (Database::getMovieActors($this->_id) as $row) {
                $person = new Person();
                $person->_id = $row['_id'];
                $person->name = $row['name'];
                $person->born = $row['born'];
                $person->_movies = $row['_movies'];
                $this->actors->add($person);
            }
        }

        return $this->actors;
    }

    /**
     * RPC method to get movie by title. Use it with source values call.
     * {"<=": {"_type": "Movie"}, "byTitle": {"()": ["The Matrix"]}}
     * @param string $title
     * @return Movie
     * @see \Deepr\tests\classes\Movie
     */
    public function byTitle(string $title): Movie
    {
        $row = Database::getMovieByTitle($title);
        if (!empty($row)) {
            $this->_id = $row['_id'];
            $this->title = $row['title'];
            $this->released = $row['released'];
            $this->tagline = $row['tagline'];
        }
        return $this;
    }

}
