<?php
namespace YouTrack\Test;

use YouTrack\Version;

/**
 * Unit test for the YouTrack Version class.
 *
 * @author shane-smith
 * Created: 2018-02-27
 */
class VersionTest extends \PHPUnit_Framework_TestCase
{

    private $filename = __DIR__  . '/testdata/version.xml';

    public function testName()
    {
        $xml = simplexml_load_file($this->filename);
        $version = new Version($xml);
        $this->assertEquals('1.2.3', $version->getName());
    }

    public function testDescription()
    {
        $xml = simplexml_load_file($this->filename);
        $version = new Version($xml);
        $this->assertEquals('A short description', $version->getDescription());
    }

    public function testReleaseDate()
    {
        $xml = simplexml_load_file($this->filename);
        $version = new Version($xml);
        $this->assertEquals(1519905600000, $version->getReleaseDate());
    }

    public function testIsReleased()
    {
        $xml = simplexml_load_file($this->filename);
        $version = new Version($xml);
        $this->assertTrue($version->isReleased());
    }

    public function testIsArchived()
    {
        $xml = simplexml_load_file($this->filename);
        $version = new Version($xml);
        $this->assertFalse($version->isArchived());
    }
}
