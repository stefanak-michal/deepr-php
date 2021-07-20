<?php

namespace Deepr\tests\classes;

/**
 * Class Database
 * Fake database with data stored in json files
 * @package Deepr\tests\classes
 * @author Michal Stefanak
 * @link https://github.com/stefanak-michal/deepr-php
 */
class Database
{
    private static $movies;
    private static $actors;

    private static function load()
    {
        if (!empty(self::$movies) && !empty(self::$actors))
            return;

        $movies = file_get_contents(__DIR__ . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'movies.json');
        if ($movies === false)
            trigger_error('Missing movies.json file', E_USER_ERROR);
        $movies = json_decode($movies, true);
        if (json_last_error() != JSON_ERROR_NONE)
            trigger_error('File movies.json has wrong structure: ' . json_last_error_msg(), E_USER_ERROR);
        self::$movies = $movies;

        $actors = file_get_contents(__DIR__ . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'actors.json');
        if ($actors === false)
            trigger_error('Missing actors.json file', E_USER_ERROR);
        $actors = json_decode($actors, true);
        if (json_last_error() != JSON_ERROR_NONE)
            trigger_error('File actors.json has wrong structure', E_USER_ERROR);
        self::$actors = $actors;
    }

    public static function getMoviesCount(): int
    {
        self::load();
        return count(self::$movies);
    }

    public static function getMovies(): array
    {
        self::load();
        return self::$movies;
    }

    public static function getMovieByTitle(string $title): array
    {
        self::load();
        foreach (self::$movies as $movie) {
            if ($movie['title'] == $title)
                return $movie;
        }
        return [];
    }

    public static function getMovieActors(int $idMovie): array
    {
        self::load();
        $output = [];
        foreach (self::$actors as $actor) {
            if (in_array($idMovie, $actor['_movies'])) {
                unset($actor['_movies']);
                $output[] = $actor;
            }
        }
        return $output;
    }
}