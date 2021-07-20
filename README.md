# deepr-php

PHP API library following Deepr specification

https://github.com/deeprjs/deepr


### Example

```php

function applyColumns(IComponent $object, array $row)
{
    foreach ($row as $column => $value) {
        if (property_exists($object, $column))
            $object->$column = $value;
    }
}

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
}

class Movie extends Collection
{
    public $_id;
    public $title;
    public $released;
    public $tagline;
}

class Root extends Collection
{
    public $movies = Movies::class;
}

$deepr = new Deepr();
$json = '{"movies": {"count": true, "[]": [0, 2],"title": true, "released": true}}';
$collection = new Root();
$deepr->invokeQuery($collection, json_decode($j, true));
echo '<pre>' . json_encode($collection->execute(), JSON_PRETTY_PRINT) . '</pre>' . PHP_EOL;

/*
{
    "movies": {
        "count": 38,
        "0": {
            "title": "The Matrix",
            "released": 1999
        },
        "1": {
            "title": "The Matrix Reloaded",
            "released": 2003
        }
    }
}
*/
```
