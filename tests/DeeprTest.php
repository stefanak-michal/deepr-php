<?php

namespace Deepr\tests;

use Deepr\Deepr;
use Deepr\tests\classes\Root;
use PHPUnit\Framework\TestCase;
use Exception;

/**
 * Class DeeprTest
 * @package Deepr\tests
 * @author Michal Stefanak
 * @link https://github.com/stefanak-michal/deepr-php
 */
class DeeprTest extends TestCase
{
    /**
     * @return Deepr|null
     */
    public function testDeepr(): ?Deepr
    {
        $deepr = new Deepr();
        $deepr::$debug = true;
        $this->assertInstanceOf(Deepr::class, $deepr);
        return $deepr;
    }

    /**
     * @depends testDeepr
     * @dataProvider jsonProvider
     * @param string $input
     * @param string $output
     * @param Deepr $deepr
     */
    public function testInvokeQueries(string $input, string $output, Deepr $deepr)
    {
        try {
            $root = new Root();
            $deepr->invokeQuery($root, json_decode($input, true));
            $result = $root->execute();
            $result = json_encode($result);
            $this->assertJsonStringEqualsJsonString($output, $result);
        } catch (Exception $e) {
            $this->markTestIncomplete($e->getMessage());
        }
    }

    /**
     * @depends testDeepr
     * @param Deepr $deepr
     */
    public function testParallel(Deepr $deepr)
    {
        $root = new Root();
        $this->expectException(Exception::class);
        $deepr->invokeQuery($root, json_decode('{"||":[]}', true));
    }

    /**
     * @depends testDeepr
     * @param Deepr $deepr
     */
    public function testMissingException(Deepr $deepr)
    {
        $root = new Root();
        $this->expectException(Exception::class);
        $deepr->invokeQuery($root, json_decode('{ "missingFunction": { "()": [] } }', true));
    }

    /**
     * Use json files in jsons directory as sample data for requests
     * @return array
     */
    public function jsonProvider()
    {
        $data = [];
        $dir = __DIR__ . DIRECTORY_SEPARATOR . 'jsons' . DIRECTORY_SEPARATOR;
        if (file_exists($dir)) {
            foreach (glob($dir . '*.json') as $file) {
                list($i, $type) = explode('-', pathinfo($file, PATHINFO_FILENAME), 2);
                $i = intval($i);
                $type = $type == 'input' ? 0 : 1;

                $json = file_get_contents($file);
                if ($json === false)
                    continue;
                $json = json_decode($json, true);
                if (json_last_error() != JSON_ERROR_NONE)
                    continue;
                $data[$i][$type] = json_encode($json);
            }
        }

        return $data;
    }
}
