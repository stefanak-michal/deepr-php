<?php

namespace Deepr\tests;

use Deepr\Deepr;
use Deepr\tests\classes\Root;
use Deepr\tests\classes\Deserializer;
use Deepr\tests\classes\Serializer;
use PHPUnit\Framework\TestCase;
use Exception;

/**
 * Class DeeprTest
 * @package Deepr\tests
 * @author Michal Stefanak
 * @link https://github.com/stefanak-michal/deepr-php
 *
 * @covers \Deepr\Deepr
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
            $result = $deepr->invokeQuery(new Root(), (new Deserializer())->deserialize(['info=>' => ['()' => [], 'date=>' => true]]));
            $this->assertEquals('2021-07-20', (new Serializer())->serialize($result));
            $deepr::$debug = false;
        } catch (Exception $e) {
            $this->markTestIncomplete($e->getMessage());
        }

        return $deepr;
    }

    /**
     * @depends      testDeepr
     * @dataProvider jsonProvider
     * @param string $input
     * @param string $output
     * @param Deepr $deepr
     */
    public function testInvokeQueries(string $input, string $output, Deepr $deepr)
    {
        echo json_encode(json_decode($input, true), JSON_PRETTY_PRINT)
            . PHP_EOL
            . json_encode(json_decode($output, true), JSON_PRETTY_PRINT);

        try {
            $root = new Root();
            $result = $deepr->invokeQuery($root, (new Deserializer())->deserialize($input));
            $this->assertJsonStringEqualsJsonString($output, (new Serializer())->serialize($result));
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
        $deepr->invokeQuery($root, (new Deserializer())->deserialize('{"||":[]}'));
    }

    /**
     * @depends testDeepr
     * @param Deepr $deepr
     */
    public function testException(Deepr $deepr)
    {
        $root = new Root();
        $this->expectException(Exception::class);
        $this->expectExceptionCode($deepr::ERROR_STRUCTURE);
        $deepr->invokeQuery($root, (new Deserializer())->deserialize('{ "[]": [] }'));
    }

    /**
     * @depends testDeepr
     * @param Deepr $deepr
     */
    public function testOptionIgnoreKeys(Deepr $deepr)
    {
        try {
            $root = new Root();
            $result = $deepr->invokeQuery($root, (new Deserializer())->deserialize('{"movies":{"[]":2,"_id":true,"title":true}}'), [
                $deepr::OPTION_IGNORE_KEYS => ['/^_/']
            ]);
            $this->assertJsonStringEqualsJsonString('{"movies":{"title":"The Matrix Revolutions"}}', (new Serializer())->serialize($result));
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
            $result = $deepr->invokeQuery($root, (new Deserializer())->deserialize('{"movies":{"[]":2,"_id":true,"title":true}}'), [
                $deepr::OPTION_IGNORE_KEYS => ['/^_/'],
                $deepr::OPTION_ACCEPT_KEYS => ['_id']
            ]);
            $this->assertJsonStringEqualsJsonString('{"movies":{"_id":10,"title":"The Matrix Revolutions"}}', (new Serializer())->serialize($result));
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
            $result = $deepr->invokeQuery($root, (new Deserializer())->deserialize('{"sayHello":{"()":["John"], "msg=>": true}}'), [
                $deepr::OPTION_CONTEXT => 'Hi',
            ]);
            $this->assertJsonStringEqualsJsonString('{"sayHello": "Hi John!"}', (new Serializer())->serialize($result));
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
            $result = $deepr->invokeQuery($root, (new Deserializer())->deserialize('{"sayHello":{"()":["John"], "msg=>": true}}'), [
                $deepr::OPTION_AUTHORIZER => function ($root, string $key, string $operation) {
                    return ($root instanceof Root && $key == 'sayHello' && $operation == 'call')
                        || (is_array($root) && $key == 'msg' && $operation == 'get');
                }
            ]);
            $this->assertJsonStringEqualsJsonString('{"sayHello": "Hello John!"}', (new Serializer())->serialize($result));
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
        $this->expectExceptionCode($deepr::ERROR_AUTHORIZER);
        $deepr->invokeQuery($root, (new Deserializer())->deserialize('{"sayHello":{"()":["John"]}}'), [
            $deepr::OPTION_AUTHORIZER => function ($root, string $key, string $operation) {
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
                $deepr::OPTION_CONTEXT => 'abc',
                $deepr::OPTION_IGNORE_KEYS => null,
                $deepr::OPTION_ACCEPT_KEYS => ['/.*/'],
                $deepr::OPTION_AUTHORIZER => function ($root, string $key, string $operation) {
                }
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
        $this->expectExceptionCode($deepr::ERROR_OPTIONS);
        $deepr->setOptions([
            $deepr::OPTION_ACCEPT_KEYS => 'this has to be array and not a string',
        ]);
    }

    /**
     * @depends testDeepr
     * @param Deepr $deepr
     */
    public function testSetOptionsArrayException(Deepr $deepr)
    {
        $this->expectException(Exception::class);
        $this->expectExceptionCode($deepr::ERROR_OPTIONS);
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
        $this->expectExceptionCode($deepr::ERROR_OPTIONS);
        $deepr->setOptions([
            $deepr::OPTION_AUTHORIZER => 'this has to be callable or null',
        ]);
    }

    /**
     * @depends testDeepr
     * @param Deepr $deepr
     */
    public function testFaultTolerant(Deepr $deepr)
    {
        try {
            $root = new Root();
            $result = $deepr->invokeQuery($root, (new Deserializer())->deserialize('{"abc?": true, "method?": {"()": []}}'));
            $this->assertEquals([], $result);
        } catch (Exception $e) {
            $this->markTestIncomplete($e->getMessage());
        }
    }
}
