<?php

namespace AppBundle\Helper;

use Symfony\Component\Config\Definition\Exception\Exception;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\DependencyInjection\ContainerInterface;

class LabsHelper extends HelperBase
{
    /** @var string */
    protected $dbName;

    /** @var \Doctrine\DBAL\Connection */
    protected $client;

    /** @var ContainerInterface */
    protected $container;

    /** @var string */
    protected $url;

    /** @var mixed[] Store DB info in case of re-requesting within the same run. */
    protected $dbInfo = [];

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    public function checkEnabled($tool)
    {
        if (!$this->container->getParameter("enable.$tool")) {
            throw new NotFoundHttpException('This tool is disabled');
        }
    }

    /**
     * Is xTools connecting to MMF Labs?
     * @return boolean
     */
    public function isLabs()
    {
        return (bool)$this->container->getParameter('app.is_labs');
    }

    /**
     * Set up LabsHelper::$client and return the database name, wiki name, and URL of a given
     * project.
     * @todo: Handle failure better
     * @return string[] With keys 'dbName', 'wikiName', 'url', and 'lang'.
     */
    public function databasePrepare($project = 'wiki')
    {
        if (isset($this->dbInfo[$project])) {
            return $this->dbInfo[$project];
        }
        if ($this->container->getParameter('app.single_wiki')) {
            $dbName = $this->container->getParameter('database_replica_name');
            $wikiName = 'wiki';
            $url = $this->container->getParameter('wiki_url');
            $lang = $this->container->getParameter('lang');
        } else {
            // First, run through our project map.  This is expected to return back
            // to the project name if there is no defined mapping.
            if ($this->container->hasParameter("app.project.$project")) {
                $project = $this->container->getParameter("app.project.$project");
            }

            // Grab the connection to the meta database
            $this->client = $this->container
                ->get('doctrine')
                ->getManager('meta')
                ->getConnection();

            // Create the query we're going to run against the meta database
            $wikiQuery = $this->client->createQueryBuilder();
            $wikiQuery
                ->select([ 'dbName', 'name', 'url', 'lang' ])
                ->from('wiki')
                ->where($wikiQuery->expr()->eq('dbname', ':project'))
                // The meta database will have the project's URL stored as https://en.wikipedia.org
                // so we need to query for it accordingly, trying different variations the user
                // might have inputted.
                ->orwhere($wikiQuery->expr()->like('url', ':projectUrl'))
                ->orwhere($wikiQuery->expr()->like('url', ':projectUrl2'))
                ->setParameter('project', $project)
                ->setParameter('projectUrl', "https://$project")
                ->setParameter('projectUrl2', "https://$project.org");
            $wikiStatement = $wikiQuery->execute();

            // Fetch the wiki data
            $wikis = $wikiStatement->fetchAll();

            // Throw an exception if we can't find the wiki
            if (count($wikis) < 1) {
                throw new Exception("Unable to find project '$project'");
            }

            // Grab the data we need out of it, using the first result
            // (in the rare event there are more than one).
            $dbName = $wikis[0]['dbName'];
            $wikiName = $wikis[0]['name'];
            $url = $wikis[0]['url'];
            $lang = $wikis[0]['lang'];
        }

        if ($this->container->getParameter('app.is_labs') && substr($dbName, -2) != '_p') {
            $dbName .= '_p';
        }

        $this->dbName = $dbName;
        $this->url = $url;

        $dbInfo = [ 'dbName' => $dbName, 'wikiName' => $wikiName, 'url' => $url, 'lang' => $lang ];
        $this->dbInfo[$project] = $dbInfo;
        return $dbInfo;
    }

    /**
     * Get project information.
     * @param string[] $projectNames Leave empty to get all projects.
     * @return array[] Each element has 'dbName', 'wikiName', 'url', and 'lang' elements.
     */
    public function getProjectsInfo($projectNames = [])
    {
        $wikiQuery = $this->client->createQueryBuilder();
        $wikiQuery->select([ 'dbName', 'name', 'url', 'lang' ])->from('wiki');
        if ($projectNames) {
            $wikiQuery->andWhere('dbName IN (:' . join(', :', $projectNames) . ')');
        }
        foreach ($projectNames as $projectName) {
            $wikiQuery->setParameter(":$projectName", $projectName);
        }
        $stmt = $wikiQuery->execute();
        $out = [];
        foreach ($stmt->fetchAll() as $wiki) {
            $out[$wiki['dbName']] = [
                'dbName' => $wiki['dbName'],
                'wikiName' => $wiki['name'],
                'url' => $wiki['url'],
                'lang' => $wiki['lang'],
            ];
        }
        $this->debug("Projects found: ", $out);
        return $out;
    }

    /**
     * All mapping tables to environment-specific names, as specified in config/table_map.yml
     * Used for example to convert revision -> revision_replica
     * https://wikitech.wikimedia.org/wiki/Help:Tool_Labs/Database#Tables_for_revision_or_logging_queries_involving_user_names_and_IDs
     *
     * @param string $table  Table name
     * @param string $dbName Database name
     *
     * @return string Converted table name
     */
    public function getTable($table, $dbName = null)
    {
        // Use the table specified in the table mapping configuration, if present.
        $mapped = false;
        if ($this->container->hasParameter("app.table.$table")) {
            $mapped = true;
            $table = $this->container->getParameter("app.table.$table");
        }

        // For 'revision' and 'logging' tables (actually views) on Labs, use the indexed versions
        // (that have some rows hidden, e.g. for revdeleted users).
        $isLoggingOrRevision = in_array($table, ['revision', 'logging', 'archive']);
        if (!$mapped && $isLoggingOrRevision && $this->isLabs()) {
            $table = $table."_userindex";
        }

        // Figure out database name.
        // Use class variable for the database name if not set via function parameter.
        $dbNameActual = $dbName ? $dbName : $this->dbName;
        if ($this->isLabs() && substr($dbNameActual, -2) != '_p') {
            // Append '_p' if this is labs.
            $dbNameActual .= '_p';
        }

        return $dbNameActual ? "$dbNameActual.$table" : $table;
    }

    // TODO: figure out how to use Doctrine to query host 'tools-db'
}
