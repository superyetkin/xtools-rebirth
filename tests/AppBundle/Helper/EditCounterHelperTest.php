<?php

namespace Tests\AppBundle\Helper;

use AppBundle\Helper\EditCounterHelper;
use Mediawiki\Api\ApiUser;
use Mediawiki\Api\MediawikiApi;
use Mediawiki\Api\MediawikiFactory;
use Mediawiki\DataModel\Content;
use Mediawiki\DataModel\PageIdentifier;
use Mediawiki\DataModel\Revision;
use Mediawiki\DataModel\Title;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DependencyInjection\Container;

class EditCounterHelperTest extends WebTestCase
{

    /** @var Container */
    protected $container;

    /** @var EditCounterHelper */
    protected $editCounterHelper;

    public function setUp()
    {
        parent::setUp();
        $client = static::createClient();
        $this->container = $client->getContainer();
        $this->editCounterHelper = new EditCounterHelper($this->container);

        // Make Admin edit some pages.
        $api = MediawikiApi::newFromPage('http://localhost:8081');
        $api->login(new ApiUser('admin', 'admin123'));
        $factory = new MediawikiFactory($api);
        $revisionSaver = $factory->newRevisionSaver();
        for ( $i = 1; $i <= 5; $i++ ) {
            $testCat = new PageIdentifier(new Title("Test page $i"));
            $content = new Content("Test page $i edited at ".date('Y-m-d H:i:s'));
            $revision = new Revision($content, $testCat, null, null, 'Admin');
            $revisionSaver->save($revision);
        }
    }

    public function testUserId()
    {
        $this->assertGreaterThan(0, $this->editCounterHelper->getUserId('Admin'));
    }

    public function testRevisionCounts()
    {
        $userId = $this->editCounterHelper->getUserId('Admin');
        $revCounts = $this->editCounterHelper->getRevisionCounts($userId);
        $this->assertEquals(5, $revCounts['total']);
    }
}
