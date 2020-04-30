<?php

namespace YouTrack\Test;

use YouTrack\Connection;

/**
 * A helper class for connection testing.
 */
class TestConnection extends Connection
{

    public function __construct()
    {
        parent::__construct('http://example.com', 'guest', 'guest');
    }

    protected function login($username, $password)
    {
        //Do nothing here.
    }
}
