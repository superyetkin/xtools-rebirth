<?php

namespace Tests\AppBundle\Helper;

use AppBundle\Helper\LabsHelper;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DependencyInjection\Container;

class LabsHelperTest extends WebTestCase
{

    /** @var Container */
    protected $container;

    /** @var LabsHelper */
    protected $labsHelper;

    public function setUp()
    {
        parent::setUp();
        $client = static::createClient();
        $this->container = $client->getContainer();
        $this->labsHelper = new LabsHelper($this->container);
    }

    public function testGetTable()
    {
        if ($this->container->getParameter('app.is_labs')) {
            // When using Labs.
            $this->assertEquals('page', $this->labsHelper->getTable('page'));
            $this->assertEquals('logging_userindex', $this->labsHelper->getTable('logging'));
        } else {
            // When using wiki databases directly.
            $this->assertEquals('page', $this->labsHelper->getTable('page'));
            $this->assertEquals('logging', $this->labsHelper->getTable('logging'));
        }
    }

    /**
     * If this is not a single-wiki installation, then we should find a list of more than one
     * wiki in the meta database.
     */
    public function testAllProjects()
    {
        $this->assertGreaterThan(0, count($this->labsHelper->allProjects()));
    }
}
