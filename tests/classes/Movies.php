<?php

namespace Deepr\tests\classes;

/**
 * Class Movies
 * Interface ILoadable is required if you want to access collection items with "[]"
 * @package Deepr\tests\classes
 * @author Michal Stefanak
 * @link https://github.com/stefanak-michal/deepr-php
 */
class Movies
{
    /**
     * {"movies": {"count": true}}
     * @var int
     */
    public $count;

    public function __construct()
    {
        $this->count = Database::getMoviesCount();
    }

    /**
     * RPC method to get movie by title
     * {"movies": {"getByTitle": {"()": ["The Matrix"]}}}
     * @param string $title
     * @return Movie
     */
    public function getByTitle(string $title): Movie
    {
        $row = Database::getMovieByTitle($title);
        $movie = new Movie();
        $movie->_id = $row['_id'];
        $movie->title = $row['title'];
        $movie->released = $row['released'];
        $movie->tagline = $row['tagline'];
        return $movie;
    }

    /**
     * This magic method is called on "[]" array access
     * @param int $offset
     * @param int|null $length
     * @return array
     */
    public function __invoke(int $offset, ?int $length): array
    {
        $output = [];
        foreach (array_slice(Database::getMovies(), $offset, $length) as $row) {
            $movie = new Movie();
            $movie->_id = $row['_id'];
            $movie->title = $row['title'];
            $movie->released = $row['released'];
            $movie->tagline = $row['tagline'];
            $output[] = $movie;
        }
        return $output;
    }

}
