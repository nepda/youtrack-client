<?php

namespace YouTrack\Test;

/**
 * Unit test for the connection class.
 *
 * @author Jens Jahnke <jan0sch@gmx.net>
 * @author Nepomuk Fraedrich <info@nepda.eu>
 * Created at: 31.03.11 12:35
 */
class ConnectionTest extends \PHPUnit_Framework_TestCase
{
    public function testCreateConnection()
    {
        $con = new TestConnection();
        $this->assertInstanceOf('YouTrack\Test\TestConnection', $con);
    }

    public function testIncorrectLoginThrowsException()
    {
        $con = new TestConnection();
        $refl = new \ReflectionClass('YouTrack\Test\TestConnection');
        $method = $refl->getMethod('handleLoginResponse');
        $method->setAccessible(true);
        $content = file_get_contents(__DIR__ . '/testdata/incorrect-login.http');
        $response = [
            'http_code' => 403
        ];
        $this->setExpectedException('\YouTrack\IncorrectLoginException');
        $method->invoke($con, $content, $response);
    }

    public function testGetFieldType()
    {
        $con = new TestConnection();

        $this->assertEquals('ownedField', $con->getFieldType('ownedField[1]'));
        $this->assertEquals('ownedField', $con->getFieldType('ownedField'));
    }

    public function testGetIssuesByFilterWithWhitespaces()
    {
        $builder = $this->getMockBuilder('\YouTrack\Connection');
        $builder->setMethods(['get']);
        $builder->disableOriginalConstructor();

        /** @var \YouTrack\Connection|\PHPUnit_Framework_MockObject_MockObject $mock */
        $mock = $builder->getMock();

        $mock->expects($this->once())
            ->method('get')
            ->with($this->equalTo('/issue?filter=Estimation%20target%3A%20today'))
            ->will($this->returnValue(new \SimpleXMLElement(file_get_contents(__DIR__ . '/testdata/issue.xml'))));

        $mock->getIssuesByFilter('Estimation target: today');
    }

    public function testGetIssuesByFilterWithWhitespacesMoreArgs()
    {
        $builder = $this->getMockBuilder('\YouTrack\Connection');
        $builder->setMethods(['get']);
        $builder->disableOriginalConstructor();

        /** @var \YouTrack\Connection|\PHPUnit_Framework_MockObject_MockObject $mock */
        $mock = $builder->getMock();

        $mock->expects($this->once())
            ->method('get')
            ->with($this->equalTo('/issue?filter=Estimation%20target%3A%20today&after=something'))
            ->will($this->returnValue(new \SimpleXMLElement(file_get_contents(__DIR__ . '/testdata/issue.xml'))));

        $mock->getIssuesByFilter('Estimation target: today', 'something');
    }
}
