<?php

namespace YouTrack\Test;

use YouTrack\AgileSetting;

/**
 * Unit test for the youtrack agile board settings class.
 *
 * @author Nepomuk Fraedrich <info@nepda.eu>
 */
class AgileBoardSettingTest extends \PHPUnit_Framework_TestCase
{

    private $singleAgileSettingFile = __DIR__ . '/testdata/agile-boards.xml';

    public function testCanCreateSimpleAgileSetting()
    {
        try {
            $agileBoard = new AgileSetting();
            $this->assertInstanceOf('\\YouTrack\\AgileSetting', $agileBoard);
        } catch (\Exception $e) {
            $this->fail();
        }
    }

    public function testCanCreateAgileSettingFromResponse()
    {
        $xml = simplexml_load_file($this->singleAgileSettingFile);
        $xml = $xml->children();
        $xml = $xml[0];
        try {
            $agileBoard = new AgileSetting($xml);
            $this->assertInstanceOf('\\YouTrack\\AgileSetting', $agileBoard);
        } catch (\Exception $e) {
            $this->fail();
        }
    }

    private function createAgileSetting()
    {
        $xml = simplexml_load_file($this->singleAgileSettingFile);
        $xml = $xml->children();
        $xml = $xml[0];
        return new AgileSetting($xml);
    }

    public function testIdIsSetAfterXmlLoad()
    {
        $agileBoard = $this->createAgileSetting();
        $this->assertEquals('76-6', $agileBoard->getId());
    }

    public function testNameIsSetAfterXmlLoad()
    {
        $agileBoard = $this->createAgileSetting();
        $this->assertEquals('Agile Board 1', $agileBoard->getName());
    }

    public function testGetProjects()
    {
        $agileBoard = $this->createAgileSetting();

        $projects = $agileBoard->getProjects();

        $this->assertCount(3, $projects);

        $p1 = $projects[0];
        $p2 = $projects[1];
        $p3 = $projects[2];

        $this->assertSame('P1', $p1->getId());
        $this->assertSame('P2', $p2->getId());
        $this->assertSame('P3', $p3->getId());
    }

    public function testGetSprints()
    {
        $agileBoard = $this->createAgileSetting();

        $sprints = $agileBoard->getSprints();

        $this->assertCount(2, $sprints);

        $s1 = $sprints[0];
        $s2 = $sprints[1];

        $this->assertSame('77-26', $s1->getId());
        $this->assertSame('77-27', $s2->getId());
    }
}
