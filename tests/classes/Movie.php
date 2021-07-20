<?php

namespace Deepr\tests\classes;

use Deepr\components\Collection;

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
     * @return Collection
     * @see \Deepr\tests\classes\Person
     */
    public function getActors(): Collection
    {
        if (is_null($this->actors)) {
            $this->actors = new Collection();
            foreach (Database::getMovieActors($this->_id) as $row) {
                $person = new Person();
                $person->_id = $row['_id'];
                $person->name = $row['name'];
                $person->born = $row['born'];
                $this->actors->add($person);
            }
        }

        return $this->actors;
    }

}
