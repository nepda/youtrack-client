<?php

namespace YouTrack\Test;

class AutoloadingTest extends \PHPUnit_Framework_TestCase
{
    public function testCreateConnectionObject()
    {
        $connectionClassExists = class_exists('\YouTrack\Connection');
        $this->assertTrue($connectionClassExists, '\YouTrack\Connection class could not be loaded!');
    }

    public function testCreateIssueObject()
    {
        $issueClassExists = class_exists('\YouTrack\Issue');
        $this->assertTrue($issueClassExists, '\YouTrack\Issue class could not be loaded!');
    }
}
