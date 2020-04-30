<?php
namespace YouTrack\Test;


use YouTrack\Role;

/**
 * Class RoleTest
 *
 * @package YouTrack
 */
class RoleTest extends \PHPUnit_Framework_TestCase
{

    private $rolePlainFile = __DIR__  . '/testdata/role-plain.xml';
    private $roleProjectFile = __DIR__  . '/testdata/role-project-ref.xml';

    public function testCreatePlainRole()
    {
        $xml = simplexml_load_file($this->rolePlainFile);
        $role = new Role($xml);
        $this->assertEquals('Admin', $role->getName());
        $this->assertCount(0, $role->getProjectRefs());
    }

    public function testCreateRoleWithProjectReference()
    {
        $xml = simplexml_load_file($this->roleProjectFile);
        $expectedId = 'RELATED_PROJECT';
        $expectedUrl = 'http://example.com/youtrack/rest/admin/project/' . $expectedId;
        $role = new Role($xml);
        $this->assertEquals('Developer', $role->getName());
        $this->assertNotEmpty($role->getProjectRefs());
        $this->assertCount(1, $role->getProjectRefs());
        $refs = $role->getProjectRefs();
        $ref0 = $refs[0];
        $this->assertEquals($expectedId, $ref0->getId());
        $this->assertEquals($expectedUrl, $ref0->getUrl());
    }
}
