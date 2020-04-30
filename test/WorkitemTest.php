<?php
namespace YouTrack\Test;

use YouTrack\User;
use YouTrack\Workitem;

/**
 * Unit test for the youtrack workitem class.
 *
 * @author Sergey Susikov <sergey.susikov@gmail.com>
 */
class WorkitemTest extends \PHPUnit_Framework_TestCase
{
    private $filename = __DIR__  . '/testdata/workitem.xml';

    public function testConstruct01()
    {
        $items = $this->loadWorkitems();
        $this->assertEquals(2, count($items));
    }

    public function testWorkItemAuthorIsUser()
    {
        $items = $this->loadWorkitems();
        $this->assertTrue($items[0]->author instanceof User);
    }

    public function testWorkitemGetDescription()
    {
        $items = $this->loadWorkitems();
        $this->assertEquals('first work item', $items[0]->description);
    }

    /**
     * @return array
     */
    private function loadWorkitems()
    {
        $items = [];
        $xml = simplexml_load_file($this->filename);
        foreach ($xml->children() as $node) {
            $items[] = new Workitem($node, null);
        }
        return $items;
    }
}
