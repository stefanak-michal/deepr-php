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
 *
 * @covers \Deepr\Deepr
 * @covers \Deepr\components\Collection
 * @covers \Deepr\components\Value
 */
class DeeprTest extends TestCase
{
    /**
     * @return Deepr|null
     */
    public function testDeepr(): ?Deepr
    {
        $deepr = new Deepr();
        $this->assertInstanceOf(Deepr::class, $deepr);

        try {
            $deepr::$debug = true;
            $result = $deepr->invokeQuery(new Root(), ['date' => ['()' => []]]);
            $this->assertEquals(['date' => '2021-07-20'], $result);
            $deepr::$debug = false;
        } catch (Exception $e) {
            $this->markTestIncomplete($e->getMessage());
        }

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
            $input = json_decode($input, true);
            if (json_last_error() != JSON_ERROR_NONE)
                throw new Exception(json_last_error_msg());
            $result = $deepr->invokeQuery($root, $input, [
                $deepr::OPTION_SV_NS => "\\Deepr\\tests\\classes\\"
            ]);
            $result = json_encode($result);
            $this->assertJsonStringEqualsJsonString($output, $result);
        } catch (Exception $e) {
            $this->markTestIncomplete($e->getMessage());
        }
    }

    /**
     * Use json files in jsons directory as sample data for requests
     * @return array
     */
    public function jsonProvider(): array
    {
        $data = [];
        $dir = __DIR__ . DIRECTORY_SEPARATOR . 'jsons' . DIRECTORY_SEPARATOR;
        if (file_exists($dir)) {
            foreach (glob($dir . '*.json') as $file) {
                list($i, $type) = explode('-', pathinfo($file, PATHINFO_FILENAME), 2);
                $type = $type == 'input' ? 0 : 1;

                $json = file_get_contents($file);
                if ($json === false)
                    continue;
                $json = json_decode($json, true);
                if (json_last_error() != JSON_ERROR_NONE)
                    continue;
                $data['json ' . $i][$type] = json_encode($json);
            }
        }

        return $data;
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
    public function testException(Deepr $deepr)
    {
        $root = new Root();
        $this->expectException(Exception::class);
        $this->expectExceptionCode(4);
        $deepr->invokeQuery($root, json_decode('{ "[]": [] }', true));
    }

    /**
     * @depends testDeepr
     * @param Deepr $deepr
     */
    public function testOptionIgnoreKeys(Deepr $deepr)
    {
        try {
            $root = new Root();
            $result = $deepr->invokeQuery($root, json_decode('{"movies":{"[]":2,"_id":true,"title":true}}', true), [
                $deepr::OPTION_IGNORE_KEYS => ['/^_/']
            ]);
            $this->assertJsonStringEqualsJsonString(json_encode($result), '{"movies":{"title":"The Matrix Revolutions"}}');
        } catch (Exception $e) {
            $this->markTestIncomplete($e->getMessage());
        }
    }

    /**
     * @depends testDeepr
     * @param Deepr $deepr
     */
    public function testOptionAcceptKeys(Deepr $deepr)
    {
        try {
            $root = new Root();
            $result = $deepr->invokeQuery($root, json_decode('{"movies":{"[]":2,"_id":true,"title":true}}', true), [
                $deepr::OPTION_IGNORE_KEYS => ['/^_/'],
                $deepr::OPTION_ACCEPT_KEYS => ['_id']
            ]);
            $this->assertJsonStringEqualsJsonString(json_encode($result), '{"movies":{"_id":10,"title":"The Matrix Revolutions"}}');
        } catch (Exception $e) {
            $this->markTestIncomplete($e->getMessage());
        }
    }

    /**
     * @depends testDeepr
     * @param Deepr $deepr
     */
    public function testOptionContext(Deepr $deepr)
    {
        try {
            $root = new Root();
            $result = $deepr->invokeQuery($root, json_decode('{"sayHello":{"()":["John"]}}', true), [
                $deepr::OPTION_CONTEXT => 'Hi',
            ]);
            $this->assertEquals(['sayHello' => 'Hi John!'], $result);
        } catch (Exception $e) {
            $this->markTestIncomplete($e->getMessage());
        }
    }

    /**
     * @depends testDeepr
     * @param Deepr $deepr
     */
    public function testOptionAuthorizer(Deepr $deepr)
    {
        try {
            $root = new Root();
            $result = $deepr->invokeQuery($root, json_decode('{"sayHello":{"()":["John"]}}', true), [
                $deepr::OPTION_AUTHORIZER => function (string $key, string $operation) {
                    return $key == 'sayHello' && $operation == 'call';
                }
            ]);
            $this->assertEquals(['sayHello' => 'Hello John!'], $result);
        } catch (Exception $e) {
            $this->markTestIncomplete($e->getMessage());
        }
    }

    /**
     * @depends testDeepr
     * @param Deepr $deepr
     */
    public function testOptionAuthorizerException(Deepr $deepr)
    {
        $root = new Root();
        $this->expectException(Exception::class);
        $this->expectExceptionCode(1);
        $deepr->invokeQuery($root, json_decode('{"sayHello":{"()":["John"]}}', true), [
            $deepr::OPTION_AUTHORIZER => function (string $key, string $operation) {
                return false;
            }
        ]);
    }

    /**
     * @depends testDeepr
     * @param Deepr $deepr
     */
    public function testSetOptions(Deepr $deepr)
    {
        try {
            $deepr->setOptions([
                $deepr::OPTION_SV_KEY => null,
                $deepr::OPTION_IGNORE_KEYS => null
            ]);
            $this->assertInstanceOf(Deepr::class, $deepr);
        } catch (Exception $e) {
            $this->markTestIncomplete($e->getMessage());
        }
    }

    /**
     * @depends testDeepr
     * @param Deepr $deepr
     */
    public function testSetOptionsStringException(Deepr $deepr)
    {
        $this->expectException(Exception::class);
        $this->expectExceptionCode(2);
        $deepr->setOptions([
            $deepr::OPTION_SV_KEY => ['this has to be string and not a array'],
        ]);
    }

    /**
     * @depends testDeepr
     * @param Deepr $deepr
     */
    public function testSetOptionsArrayException(Deepr $deepr)
    {
        $this->expectException(Exception::class);
        $this->expectExceptionCode(2);
        $deepr->setOptions([
            $deepr::OPTION_IGNORE_KEYS => 'has to be array or empty value',
        ]);
    }

    /**
     * @depends testDeepr
     * @param Deepr $deepr
     */
    public function testSetOptionsCallableException(Deepr $deepr)
    {
        $this->expectException(Exception::class);
        $this->expectExceptionCode(2);
        $deepr->setOptions([
            $deepr::OPTION_AUTHORIZER => 'this has to be callable or null',
        ]);
    }
}
