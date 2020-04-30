<?php
namespace YouTrack\Test;

use YouTrack\BaseObject;

/**
 * Unit test for the youtrack object class.
 *
 * @author Jens Jahnke <jan0sch@gmx.net>
 * Created at: 08.04.11 13:55
 */
class BaseObjectTest extends \PHPUnit_Framework_TestCase
{

    private $filename = __DIR__ . '/testdata/issue.xml';

    public function testConstruct01()
    {
        $xml = simplexml_load_file($this->filename);
        $item = new BaseObject($xml);
        $this->assertEquals('T', $item->__get('projectShortName'));
    }

    public function testGet01()
    {
        $item = new BaseObject();
        $this->assertNull($item->__get('foo'));
    }

    public function testConvertTimestampToEuropeBerlin()
    {
        $backupTZ = date_default_timezone_get();
        date_default_timezone_set('Europe/Berlin');
        $item = new BaseObject();

        // http://www.epochconverter.com/timezones?q=1484558909&tz=Europe%2FBerlin
        $timestamp = '1484558909107';
        $expectedDate = '2017-01-16 10:28:29';
        $date = $item->convertTimestampToDateTime($timestamp);

        $this->assertSame($expectedDate, $date->format('Y-m-d H:i:s'));

        date_default_timezone_set($backupTZ);
    }

    public function testGet02()
    {
        $xml = simplexml_load_file($this->filename);
        $item = new BaseObject($xml);
        $this->assertEquals('T-2', $item->__get('id'));
    }

    public function testGet03()
    {
        $xml = simplexml_load_file($this->filename);
        $item = new BaseObject($xml);
        $this->assertEquals('T-2', $item->getId());
    }

    public function testSet01()
    {
        $item = new BaseObject();
        $value = 'bar';
        $item->__set('foo', $value);
        $this->assertEquals($value, $item->__get('foo'));
    }

    public function testSet02()
    {
        $item = new BaseObject();
        $value = 'bar';
        $item->setFoo($value);
        $this->assertEquals($value, $item->__get('foo'));
    }
}
