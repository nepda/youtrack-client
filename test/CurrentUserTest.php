<?php
namespace YouTrack\Test;

use YouTrack\CurrentUser;

/**
 * Unit test for fetching current user.
 *
 * @author Juraj Juricic <juraj.juricic@gmail.com>
 */
class CurrentUserTest extends \PHPUnit_Framework_TestCase
{
    private $filename = __DIR__  . '/testdata/currentuser.xml';

    public function testConstruct()
    {
        $currentUser = $this->loadCurrentUser();
        $this->assertEquals('JJ', $currentUser->login);
    }

    public function testFields()
    {
        $currentUser = $this->loadCurrentUser();
        $this->assertEquals('JJ', $currentUser->login);

        $this->assertEquals('TEST', $currentUser->lastCreatedProject);
        $this->assertEquals('juraj.juricic@gmail.com', $currentUser->email);
        $this->assertEquals('Juraj Juricic', $currentUser->fullName);
    }

    /**
     * @return CurrentUser
     */
    private function loadCurrentUser()
    {
        $xml = simplexml_load_file($this->filename);

        return new CurrentUser($xml);
    }
}
