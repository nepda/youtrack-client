<?php
$root = true;
include_once 'config.php';

$youtrack = new YouTrack\Connection(
    YOUTRACK_URL,
    YOUTRACK_USERNAME,
    YOUTRACK_PASSWORD
);

$projects = $youtrack->getAccessibleProjects(true);

foreach ($projects as $project) {
    echo $project->getShortName() . PHP_EOL;
}
