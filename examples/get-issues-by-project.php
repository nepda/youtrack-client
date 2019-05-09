<?php

include_once 'config.php';

$youtrack = new YouTrack\Connection(
    YOUTRACK_URL,
    YOUTRACK_USERNAME,
    YOUTRACK_PASSWORD
);

$issues = $youtrack->getIssues('GSHOP', '#Resolved');

foreach ($issues as $issue) {
    echo $issue->getId() . PHP_EOL;
}
