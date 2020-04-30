<?php
namespace YouTrack\Test;

use YouTrack\Exception as YouTrackException;

/**
 * Unit tests for the youtrack exception class.
 *
 * @author Jens Jahnke <jan0sch@gmx.net>
 * Created at: 30.03.11 10:42
 */
class ExceptionTest extends \PHPUnit_Framework_TestCase
{
    public function testConstruct01()
    {
        $url = 'http://example.com';
        $response = [
            'http_code' => 200,
        ];
        $content = '';
        $e = new YouTrackException($url, $response, $content);
        $this->assertEquals("Error for 'http://example.com': 200", $e->getMessage());
    }

    public function testConstruct02()
    {
        $url = 'http://example.com';
        $response = [
            'http_code' => 404,
        ];
        $content = '';
        $e = new YouTrackException($url, $response, $content);
        $this->assertEquals("Error for 'http://example.com': 404", $e->getMessage());
    }

    public function testConstruct03()
    {
        $url = 'http://example.com';
        $response = [
            'http_code' => 500,
            'content_type' => 'text/html; charset=utf8',
        ];
        $content = '';
        $e = new YouTrackException($url, $response, $content);
        $this->assertEquals("Error for 'http://example.com': 500", $e->getMessage());
    }

    public function testConstruct04()
    {
        $url = 'http://example.com';
        $response = [
            'http_code' => 403,
            'content_type' => 'text/plain',
        ];
        $content = '<error>You have no rights to read user.</error>';
        $e = new YouTrackException($url, $response, $content);
        $this->assertEquals("Error for 'http://example.com': 403: You have no rights to read user.", $e->getMessage());
    }
}
