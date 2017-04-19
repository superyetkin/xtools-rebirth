<?php

namespace AppBundle\Controller;

use AppBundle\Helper\ApiHelper;
use AppBundle\Helper\AutomatedEditsHelper;
use AppBundle\Helper\EditCounterHelper;
use AppBundle\Helper\LabsHelper;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

class EditCounterController extends Controller
{

    /** @var EditCounterHelper */
    protected $editCounterHelper;

    /** @var ApiHelper */
    protected $apiHelper;

    /** @var LabsHelper */
    protected $labsHelper;

    /**
     * Every action in this controller calls this first.
     * @param string|boolean $project
     */
    public function init($project = false, $username = false)
    {
        $this->labsHelper = $this->get('app.labs_helper');
        $this->editCounterHelper = $this->get('app.editcounter_helper');
        $this->apiHelper = $this->get('app.api_helper');
        $this->labsHelper->checkEnabled("ec");
        if ($project) {
            $this->labsHelper->databasePrepare($project);
        }
    }

    /**
     * @Route("/ec", name="ec")
     * @Route("/ec", name="EditCounter")
     * @Route("/ec/", name="EditCounterSlash")
     * @Route("/ec/index.php", name="EditCounterIndexPhp")
     * @Route("/ec/{project}", name="EditCounterProject")
     */
    public function indexAction(Request $request, $project = null)
    {
        $queryProject = $request->query->get('project');
        $username = $request->query->get('username');

        if (($project || $queryProject) && $username) {
            $routeParams = [ 'project'=>($project ?: $queryProject), 'username' => $username ];
            return $this->redirectToRoute("EditCounterResult", $routeParams);
        } elseif (!$project && $queryProject) {
            return $this->redirectToRoute("EditCounterProject", [ 'project'=>$queryProject ]);
        }

        $this->init($project);

        // Otherwise fall through.
        return $this->render('editCounter/index.html.twig', [
            "xtPageTitle" => "tool_ec",
            "xtSubtitle" => "tool_ec_desc",
            'xtPage' => "ec",
            'xtTitle' => "tool_ec",
            'project' => $project,
        ]);
    }

    /**
     * @Route("/ec/{project}/{username}", name="EditCounterResult")
     */
    public function resultAction(Request $request, $project, $username)
    {
        $this->init($project, $username);

        // Check, clean, and get inputs.
        $username = ucfirst($username);
        $dbValues = $this->labsHelper->databasePrepare($project);
        $dbName = $dbValues["dbName"];
        $wikiName = $dbValues["wikiName"];
        $url = $dbValues["url"];

        /** @var AutomatedEditsHelper $automatedEditsHelper */
        $automatedEditsHelper = $this->get('app.automated_edits_helper');
        $userId = $this->editCounterHelper->getUserId($username);
        if (0 === $userId) {
            // If the user doesn't exist.
            $request->getSession()->getFlashBag()->set('notice', 'user-not-found');
            return $this->redirectToRoute('ec');
        }

        $automatedEditsSummary = $automatedEditsHelper->getEditsSummary($userId);

        // Give it all to the template.
        return $this->render('editCounter/result.html.twig', [
            'xtTitle' => 'tool_ec',
            'xtPage' => 'ec',
            'base_dir' => realpath($this->getParameter('kernel.root_dir').'/..'),
            'is_labs' => $this->labsHelper->isLabs(),
            'username' => $username,

            // Project.
            'project' => $project,
            'wiki' => $dbName,
            'name' => $wikiName,
            'url' => $url,

            // Automated edits.
            'auto_edits' => $automatedEditsSummary,
        ]);
    }

    /**
     * @Route("/ec-generalstats/{project}/{username}", name="EditCounterGeneralStats")
     */
    public function generalStatsAction($project, $username)
    {
        $this->init($project);

        /** @var AutomatedEditsHelper $automatedEditsHelper */
        $automatedEditsHelper = $this->get('app.automated_edits_helper');

        $dbValues = $this->labsHelper->databasePrepare($project);
        $url = $dbValues["url"];
        $username = ucfirst($username);
        $isSubRequest = $this->get('request_stack')->getParentRequest() !== null;
        $userId = $this->editCounterHelper->getUserId($username);
        $revisionCounts = $this->editCounterHelper->getRevisionCounts($userId);
        $pageCounts = $this->editCounterHelper->getPageCounts($username, $revisionCounts['total']);
        $logCounts = $this->editCounterHelper->getLogCounts($userId);
        $automatedEditsSummary = $automatedEditsHelper->getEditsSummary($userId);
        $topProjectsEditCounts = $this->editCounterHelper->getTopProjectsEditCounts($url, $username);

        return $this->render('editCounter/general_stats.html.twig', [
            'xtTitle' => 'tool_ec',
            'xtPage' => 'ec',
            'is_sub_request' => $isSubRequest,
            'is_labs' => $this->labsHelper->isLabs(),

            // Project information.
            'project' => $project,
            'url' => $url,
            'namespaces' => $this->apiHelper->namespaces($project),

            // User and groups.
            'username' => $username,
            'user_id' => $userId,
            'user_groups' => $this->apiHelper->groups($project, $username),
            'global_groups' => $this->apiHelper->globalGroups($project, $username),

            // Revision counts.
            'deleted_edits' => $revisionCounts['deleted'],
            'total_edits' => $revisionCounts['total'],
            'live_edits' => $revisionCounts['live'],
            'first_rev' => $revisionCounts['first'],
            'latest_rev' => $revisionCounts['last'],
            'days' => $revisionCounts['days'],
            'avg_per_day' => $revisionCounts['avg_per_day'],
            'rev_24h' => $revisionCounts['24h'],
            'rev_7d' => $revisionCounts['7d'],
            'rev_30d' => $revisionCounts['30d'],
            'rev_365d' => $revisionCounts['365d'],
            'rev_small' => $revisionCounts['small'],
            'rev_large' => $revisionCounts['large'],
            'with_comments' => $revisionCounts['with_comments'],
            'without_comments' => $revisionCounts['live'] - $revisionCounts['with_comments'],
            'minor_edits' => $revisionCounts['minor_edits'],
            'nonminor_edits' => $revisionCounts['live'] - $revisionCounts['minor_edits'],
            'auto_edits_total' => array_sum($automatedEditsSummary),

            // Page counts.
            'uniquePages' => $pageCounts['unique'],
            'pagesCreated' => $pageCounts['created'],
            'pagesMoved' => $pageCounts['moved'],
            'editsPerPage' => $pageCounts['edits_per_page'],

            // Log counts (keys are 'log name'-'action').
            'pagesThanked' => $logCounts['thanks-thank'],
            'pagesApproved' => $logCounts['review-approve'], // Merged -a, -i, and -ia approvals.
            'pagesPatrolled' => $logCounts['patrol-patrol'],
            'usersBlocked' => $logCounts['block-block'],
            'usersUnblocked' => $logCounts['block-unblock'],
            'pagesProtected' => $logCounts['protect-protect'],
            'pagesUnprotected' => $logCounts['protect-unprotect'],
            'pagesDeleted' => $logCounts['delete-delete'],
            'pagesDeletedRevision' => $logCounts['delete-revision'],
            'pagesRestored' => $logCounts['delete-restore'],
            'pagesImported' => $logCounts['import-import'],
            'files_uploaded' => $logCounts['upload-upload'],
            'files_modified' => $logCounts['upload-overwrite'],
            'files_uploaded_commons' => $logCounts['files_uploaded_commons'],

            // Other projects.
            'top_projects_edit_counts' => $topProjectsEditCounts,
        ]);
    }

    /**
     * @Route("/ec-namespacetotals/{project}/{username}", name="EditCounterNamespaceTotals")
     */
    public function namespaceTotalsAction($project, $username)
    {
        $this->init($project);
        $username = ucfirst($username);
        $isSubRequest = $this->get('request_stack')->getParentRequest() !== null;
        $namespaceTotals = $this->editCounterHelper->getNamespaceTotals($username);

        return $this->render('editCounter/namespace_totals.html.twig', [
            'xtTitle' => 'tool_ec',
            'xtPage' => 'ec',
            'is_sub_request' => $isSubRequest,
            'namespaces' => $this->apiHelper->namespaces($project),
            'namespace_totals' => $namespaceTotals,
            'namespace_total' => array_sum($namespaceTotals),
        ]);
    }

    /**
     * @Route("/ec-timecard/{project}/{username}", name="EditCounterTimeCard")
     */
    public function timecardAction($project, $username)
    {
        $this->init($project);
        $this->labsHelper->databasePrepare($project);
        $username = ucfirst($username);
        $isSubRequest = $this->get('request_stack')->getParentRequest() !== null;
        $datasets = $this->editCounterHelper->getTimeCard($username);
        return $this->render('editCounter/timecard.html.twig', [
            'xtTitle' => 'tool_ec',
            'xtPage' => 'ec',
            'is_sub_request' => $isSubRequest,
            'datasets' => $datasets,
        ]);
    }

    /**
     * @Route("/ec-yearcounts/{project}/{username}", name="EditCounterYearCounts")
     */
    public function yearcountsAction($project, $username)
    {
        $this->init($project);
        $this->labsHelper->databasePrepare($project);
        $username = ucfirst($username);
        $isSubRequest = $this->container->get('request_stack')->getParentRequest() !== null;
        $yearcounts = $this->editCounterHelper->getYearCounts($username);
        return $this->render('editCounter/yearcounts.html.twig', [
            'xtTitle' => 'tool_ec',
            'xtPage' => 'ec',
            'is_sub_request' => $isSubRequest,
            'namespaces' => $this->apiHelper->namespaces($project),
            'yearcounts' => $yearcounts,
        ]);
    }

    /**
     * @Route("/ec-monthcounts/{project}/{username}", name="EditCounterMonthCounts")
     */
    public function monthcountsAction($project, $username)
    {
        $this->init($project);
        $this->labsHelper->databasePrepare($project);
        $username = ucfirst($username);
        $monthlyTotalsByNamespace = $this->editCounterHelper->getMonthCounts($username);
        $isSubRequest = $this->container->get('request_stack')->getParentRequest() !== null;
        return $this->render('editCounter/monthcounts.html.twig', [
            'xtTitle' => 'tool_ec',
            'xtPage' => 'ec',
            'is_sub_request' => $isSubRequest,
            'month_counts' => $monthlyTotalsByNamespace,
            'namespaces' => $this->apiHelper->namespaces($project),
        ]);
    }

    /**
     * @Route("/ec-latestglobal/{project}/{username}", name="EditCounterLatestGlobal")
     */
    public function latestglobalAction($project, $username)
    {
        $this->init($project);
        $info = $this->labsHelper->databasePrepare($project);
        $username = ucfirst($username);

        $topProjectsEditCounts = $this->editCounterHelper->getTopProjectsEditCounts(
            $info['url'],
            $username
        );
        $recentGlobalContribs = $this->editCounterHelper->getRecentGlobalContribs(
            $username,
            array_keys($topProjectsEditCounts)
        );

        $isSubRequest = $this->container->get('request_stack')->getParentRequest() !== null;
        return $this->render('editCounter/latest_global.html.twig', [
            'xtTitle' => 'tool_ec',
            'xtPage' => 'ec',
            'is_sub_request' => $isSubRequest,
            'latest_global_contribs' => $recentGlobalContribs,
            'username' => $username,
            'project' => $project,
            'namespaces' => $this->apiHelper->namespaces($project),
        ]);
    }
}
