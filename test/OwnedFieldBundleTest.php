<?php
namespace YouTrack\Test;

use YouTrack\OwnedField;
use YouTrack\OwnedFieldBundle;

class OwnedFieldBundleTest extends \PHPUnit_Framework_TestCase
{

    private $filename = __DIR__  . '/testdata/owned_field_bundle.xml';

    public function testGetOwnedFieldBundleElements()
    {
        $xml = simplexml_load_file($this->filename);
        $bundle = new OwnedFieldBundle($xml);

        /** @var OwnedField[] $bundleElements */
        $bundleElements = $bundle->getValues();

        $this->assertCount(3, $bundleElements);

        $this->assertSame('Test', $bundleElements[0]->getName());
        $this->assertSame(4, $bundleElements[0]->getColorIndex());
        $this->assertSame('', $bundleElements[0]->getDescription());
        $this->assertSame('<no user>', $bundleElements[0]->getOwner());

        $this->assertSame('TestWithOwner', $bundleElements[1]->getName());
        $this->assertSame(4, $bundleElements[1]->getColorIndex());
        $this->assertSame('', $bundleElements[1]->getDescription());
        $this->assertSame('userX', $bundleElements[1]->getOwner());

        $this->assertSame('TestWitDescription', $bundleElements[2]->getName());
        $this->assertSame(1, $bundleElements[2]->getColorIndex());
        $this->assertSame('Test', $bundleElements[2]->getDescription());
        $this->assertSame('<no user>', $bundleElements[2]->getOwner());
    }
}
