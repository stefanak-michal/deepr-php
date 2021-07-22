<?php

namespace Deepr\tests\classes;

use Deepr\components\Collection;
use Deepr\components\IComponent;
use Deepr\components\ILoadable;

/**
 * Class Movies
 * Interface ILoadable is required if you want to access collection items with "[]"
 * @package Deepr\tests\classes
 * @author Michal Stefanak
 * @link https://github.com/stefanak-michal/deepr-php
 */
class Movies extends Collection implements ILoadable
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
     * This method is implemented by interface ILoadable and it's called on "[]"
     * {"movies": {"[]": []}}
     * @param int $offset
     * @param int|null $length
     * @return Collection
     * @see \Deepr\tests\classes\Movie
     */
    public function load(int $offset, ?int $length): Collection
    {
        $items = new Collection();
        foreach (array_slice(Database::getMovies(), $offset, $length) as $row) {
            $movie = new Movie();
            $movie->_id = $row['_id'];
            $movie->title = $row['title'];
            $movie->released = $row['released'];
            $movie->tagline = $row['tagline'];
            $items->add($movie);
        }
        return $items;
    }

    /**
     * RPC method to get movie by title
     * {"movies":{"getByTitle":{"()":["The Matrix"]}}}
     * @param string $title
     * @return Collection
     * @see \Deepr\tests\classes\Movie
     */
    public function getByTitle(string $title): Collection
    {
        $collection = new self();

        $movie = new Movie();
        $row = Database::getMovieByTitle($title);
        $movie->_id = $row['_id'];
        $movie->title = $row['title'];
        $movie->released = $row['released'];
        $movie->tagline = $row['tagline'];
        $collection->add($movie);

        return $collection;
    }
}
