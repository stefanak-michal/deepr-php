<?php

interface DataMethods
{
    public static function getMoviesCount(): int;
    public static function getMovies(): array;
    public static function getMovieByTitle(string $title): array;
    public static function getMoviesByReleased(int $year): array;
}

if (file_exists('DB.php'))
    require_once 'DB.php';
else
    trigger_error('Missing source DB class. You have to create own. Use DataMethods interface to implement all required methods.', E_USER_ERROR);

require_once 'Deepr.php';

// Our data are only Movie and Person ..check classes below to see available properties

/**
 * Class Movie
 */
class Movie extends AComponent
{
    public $_id;
    public $title;
    public $released;
    public $tagline;
}

/**
 * Class Person
 */
class Person extends AComponent
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

}

/**
 * Class Movies
 * Collection of movies
 */
class Movies extends Collection
{

    public function count(): int
    {
        return DB::getMoviesCount();
    }

    public function load()
    {
        foreach (DB::getMovies() as $row) {
            $movie = new Movie();
            foreach ($row as $column => $value) {
                if (property_exists($movie, $column))
                    $movie->$column = $value;
            }
            $this->add($movie);
        }
    }

    public function getByTitle(string $title): ?Movie
    {
        if (parent::count()) {
            foreach ($this->getChildren() as $child) {
                if ($child instanceof Movie && $child->title == $title)
                    return $child;
            }
        }

        $movie = new Movie();
        $row = DB::getMovieByTitle($title);
        foreach ($row as $column => $value) {
            if (property_exists($movie, $column))
                $movie->$column = $value;
        }
        return $movie;
    }

    public function getByReleased(int $year): Collection
    {
        $movies = new Movies();
        if (parent::count()) {
            foreach ($this->getChildren() as $child) {
                if ($child instanceof Movie && $child->released == $year)
                    $movies->add($child);
            }

        } else {
            foreach (DB::getMoviesByReleased($year) as $row) {
                $movie = new Movie();
                foreach ($row as $column => $value) {
                    if (property_exists($movie, $column))
                        $movie->$column = $value;
                }
                $movies->add($movie);
            }
        }

        return $movies;
    }
}



/*
 * Testing json requests
 */
$json = [
    '{"movies": {"count": true}}',
    '{"movies": {"[]": [0, 2],"title": true, "released": true}}',
    '{"movies": {"[]": [35],"title": true}}',
    '{"movies": {"[]": 20,"title=>": true}}',
    '{"movies": {"[]": 20,"title=>Title": true}}',
    '{"movies": {"count": true,"=>items": {"[]": [5, 5],"title": true}}}',
    '{"movies": {"count": true,"items=>": {"[]": [5, 5],"title": true}}}',
    '{"movies": {"count": true,"=>items": {"[]": [5, 5],"title=>": true}}}',
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
//    '{"movies": {"[]": [0,5], "title": true}, "persons": {"[]": [0,5], "name": true}}',
//    '{"movies":{"filter=>":{"()":[{"released":1996}],"sort=>":{"()":[{"by":"title"}],"skip=>":{"()":[1],"limit=>":{"()":[10],"=>":{"[]":[],"title":true,"released":true}}}}}}}',
//    '{"movies":[{"getByTitle=>":{"()":["Top Gun"],"title":true}},{"getByTitle=>":{"()":["The Matrix"],"title":true}}]}',
//    '{"getMovies=>actionMovies":{"()":[],"=>":{"[]":[],"title":true}}}',
//    '{"movies": {"[]": [], "title": true, "getActors=>actors": { "()": [], "name": true } }}'
];

$deepr = new Deepr();
foreach ($json as $j) {
    echo '###############';
    echo '<pre>' . $j . '</pre>' . PHP_EOL;
    $collection = new Root();
    $deepr->invokeQuery($collection, json_decode($j, true));
//    var_dump($collection);
//    var_dump($result->execute());
    echo '<pre>' . json_encode($collection->execute(), JSON_PRETTY_PRINT) . '</pre>' . PHP_EOL;
}
