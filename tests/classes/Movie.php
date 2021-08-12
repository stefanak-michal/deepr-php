<?php

namespace Deepr\tests\classes;

/**
 * Class Movie
 * @package Deepr\tests\classes
 * @author Michal Stefanak
 * @link https://github.com/stefanak-michal/deepr-php
 */
class Movie
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
    /**
     * {"movies": {"[]":[], "tagline": true}}
     * @var string
     */
    public $tagline;

    /**
     * @var array
     */
    private $actors;

    /**
     * RPC method to get actors of movie
     * {"movies":{"[]":[],"=>":{"getActors":{"()":[]}}}}
     * @return array
     * @see \Deepr\tests\classes\Person
     */
    public function getActors(): array
    {
        if (is_null($this->actors)) {
            $this->actors = [];
            foreach (Database::getMovieActors($this->_id) as $row) {
                $person = new Person();
                $person->_id = $row['_id'];
                $person->name = $row['name'];
                $person->born = $row['born'];
                $person->_movies = $row['_movies'];
                $this->actors[] = $person;
            }
        }

        return $this->actors;
    }

    /**
     * RPC method to get movie by title.
     * {"<=": {"_type": "Movie"}, "byTitle": {"()": ["The Matrix"]}}
     * @param string $title
     * @return Movie
     * @see \Deepr\tests\classes\Movie
     */
    public function byTitle(string $title)
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
