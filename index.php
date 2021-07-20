<?php

interface DataMethods
{
    public static function getMoviesCount(): int;
    public static function getMovies(): array;
    public static function getMovieByTitle(string $title): array;
    public static function getMoviesByReleased(int $year): array;
    public static function getMovieActors(int $idMovie): array;
}

if (file_exists('DB.php'))
    require_once 'DB.php';
else
    trigger_error('Missing source DB class. You have to create own. Use DataMethods interface to implement all required methods.', E_USER_ERROR);

require_once 'src' . DIRECTORY_SEPARATOR . 'autoload.php';

use \Deepr\Deepr;
use \Deepr\components\{IComponent, Collection, ILoadable};

// Our data are only Movie and Person ..check classes below to see available properties

/**
 * Helper function to add database info into object
 * @param IComponent $object
 * @param array $row
 */
function applyColumns(IComponent $object, array $row)
{
    foreach ($row as $column => $value) {
        if (property_exists($object, $column))
            $object->$column = $value;
    }
}

/**
 * Class Movie
 */
class Movie extends Collection
{
    public $_id;
    public $title;
    public $released;
    public $tagline;

    private $actors;

    public function getActors()
    {
        if (is_null($this->actors)) {
            $this->actors = new Collection();
            foreach (DB::getMovieActors($this->_id) as $row) {
                $person = new Person();
                applyColumns($person, $row);
                $this->actors->add($person);
            }
        }

        return $this->actors;
    }

}

/**
 * Class Person
 */
class Person extends Collection
{
    public $_id;
    public $name;
    public $born;
}


/**
 * Class Root
 * Main collection
 */
class Root extends Collection
{
    /**
     * @var string It can also be instance of specified class
     */
    public $movies = Movies::class;

    public function __construct()
    {
        //$this->movies = new Movies();
    }

    public function load(): array
    {
        // TODO: Implement load() method.
    }


}

/**
 * Class Movies
 * Collection of movies
 */
class Movies extends Collection implements ILoadable
{
    public $count;

    public function __construct()
    {
        $this->count = DB::getMoviesCount();
    }

    public function load(): array
    {
        $items = [];
        foreach (DB::getMovies() as $row) {
            $movie = new Movie();
            applyColumns($movie, $row);
            $items[] = $movie;
        }
        return $items;
    }

    public function getByTitle(string $title): Collection
    {
        $collection = new self();

        $movie = new Movie();
        $row = DB::getMovieByTitle($title);
        applyColumns($movie, $row);
        $collection->add($movie);

        return $collection;
    }

    public function getByReleased(int $year): Collection
    {
        $collection = new self();

        foreach (DB::getMoviesByReleased($year) as $row) {
            $movie = new Movie();
            applyColumns($movie, $row);
            $collection->add($movie);
        }

        return $collection;
    }
}



/*
 * Testing json requests
 */
$json = [
    '{"movies": {"count": true}}',
    '{"movies": {"[]": [0, 2],"title": true, "released": true}}',
    '{"movies": {"[]": [35],"title": true}}',
    '{
  "movies": {
    "[]": 20,
    "=>": {
      "title": true

    }
  }
}',
    '{
  "movies": {
    "[]": 20,
    "=>": {
      "title": true,
      "getActors": {
        "()": [],
        "name": true
      }
    }
  }
}',
    '{
  "movies": {
    "[]": 20,
    "=>": {
      "title": true,
      "getActors=>actors": {
        "()": [],
        "name": true
      }
    }
  }
}',

    '{"movies": {"[]": 20,"title=>Title": true}}',
    '{"movies": {"=>items": {"[]": [5, 5],"title": true}}}',
    '{"movies": {"items=>": {"[]": [5, 5],"title": true}}}',
    '{"movies": {"=>items": {"[]": [5, 5],"title=>": true}}}',
    '{"movies": {"=>": {"[]": [5, 2],"title": true}}}',
    '{"movies": {"getByTitle=>": {"()":["The Matrix"], "title": true, "released": true}}}',
    '{"movies": {"getByTitle=>movie": {"()":["The Matrix"], "title": true, "released": true}}}',
    '{"movies": {"getByReleased=>": {"()":[1996], "title": true, "tagline": true}}}',
    '{
  "movies": [
    {
      "getByTitle=>": {
        "()": ["Top Gun"],
        "title": true
      }
    },
    {
      "getByTitle=>": {
        "()": ["The Matrix"],
        "title": true
      }
    }
  ]
}',
];

$deepr = new Deepr();
$deepr::$debug = true;
foreach ($json as $j) {
    echo '###############';
    echo '<pre>' . $j . '</pre>' . PHP_EOL;
    $collection = new Root();
    $deepr->invokeQuery($collection, json_decode($j, true));
//    var_dump($collection);
//    var_dump($result->execute());
    echo '<pre>' . json_encode($collection->execute(), JSON_PRETTY_PRINT) . '</pre>' . PHP_EOL;
}
