<?php

namespace Xibo\Controller;

define('BLACKLIST_ALL', "All");
define('BLACKLIST_SINGLE', "Single");

use Jenssegers\Date\Date;
use Slim\Log;
use Stash\Interfaces\PoolInterface;
use Xibo\Entity\Bandwidth;
use Xibo\Entity\Display;
use Xibo\Entity\Playlist;
use Xibo\Entity\Region;
use Xibo\Entity\RequiredFile;
use Xibo\Entity\Schedule;
use Xibo\Entity\Stat;
use Xibo\Entity\UserGroup;
use Xibo\Entity\Widget;
use Xibo\Exception\ControllerNotImplemented;
use Xibo\Exception\DeadlockException;
use Xibo\Exception\NotFoundException;
use Xibo\Exception\XMDSFault;
use Xibo\Factory\BandwidthFactory;
use Xibo\Factory\DataSetFactory;
use Xibo\Factory\DayPartFactory;
use Xibo\Factory\DisplayEventFactory;
use Xibo\Factory\DisplayFactory;
use Xibo\Factory\LayoutFactory;
use Xibo\Factory\MediaFactory;
use Xibo\Factory\ModuleFactory;
use Xibo\Factory\NotificationFactory;
use Xibo\Factory\RegionFactory;
use Xibo\Factory\RequiredFileFactory;
use Xibo\Factory\ScheduleFactory;
use Xibo\Factory\UserFactory;
use Xibo\Factory\UserGroupFactory;
use Xibo\Factory\WidgetFactory;
use Xibo\Helper\Random;
use Xibo\Service\ConfigServiceInterface;
use Xibo\Service\DateServiceInterface;
use Xibo\Service\LogService;
use Xibo\Service\LogServiceInterface;
use Xibo\Service\SanitizerServiceInterface;
use Xibo\Storage\StorageServiceInterface;
require_once PROJECT_ROOT . '/lib/Helper/ItemIDDef.php';

/**
 * Class XMDSHandler
 * @package Xibo\Controller
 */
class XMDSHandler extends Base
{
    /**
     * @var Display
     */
    protected $display;

    /**
     * @var LogProcessor
     */
    protected $logProcessor;

    /** @var  PoolInterface */
    private $pool;

    /** @var  StorageServiceInterface */
    private $store;

    /** @var  RequiredFileFactory */
    protected $requiredFileFactory;

    /** @var  ModuleFactory */
    protected $moduleFactory;

    /** @var  LayoutFactory */
    protected $layoutFactory;

    /** @var  DataSetFactory */
    protected $dataSetFactory;

    /** @var  DisplayFactory */
    protected $displayFactory;

    /** @var  UserGroupFactory */
    protected $userGroupFactory;

    /** @var  BandwidthFactory */
    protected $bandwidthFactory;

    /** @var  MediaFactory */
    protected $mediaFactory;

    /** @var  WidgetFactory */
    protected $widgetFactory;

    /** @var  RegionFactory */
    protected $regionFactory;

    /** @var  NotificationFactory */
    protected $notificationFactory;

    /** @var  DisplayEventFactory */
    protected $displayEventFactory;

    /** @var  ScheduleFactory */
    protected $scheduleFactory;

    /** @var  DayPartFactory */
    protected $dayPartFactory;

    /**
     * Set common dependencies.
     * @param LogServiceInterface $log
     * @param SanitizerServiceInterface $sanitizerService
     * @param \Xibo\Helper\ApplicationState $state
     * @param \Xibo\Entity\User $user
     * @param \Xibo\Service\HelpServiceInterface $help
     * @param DateServiceInterface $date
     * @param ConfigServiceInterface $config
     * @param PlayerActionServiceInterface $playerAction
     * @param DisplayFactory $displayFactory
     * @param DisplayGroupFactory $displayGroupFactory
     * @param LayoutFactory $layoutFactory
     * @param ModuleFactory $moduleFactory
     * @param MediaFactory $mediaFactory
     * @param CommandFactory $commandFactory
     * @param ScheduleFactory $scheduleFactory
     */
    public function __construct($log, $sanitizerService, $state, $user, $help, $date, $config, $pool, $store, 
                                    $requiredFileFactory, $moduleFactory, $layoutFactory, $dataSetFactory, $displayFactory, $userGroupFactory, 
                                    $bandwidthFactory, $mediaFactory, $widgetFactory, $regionFactory, $notificationFactory, $displayEventFactory, 
                                    $scheduleFactory, $dayPartFactory)
    {
        $this->setCommonDependencies($log, $sanitizerService, $state, $user, $help, $date, $config);

        
        $this->pool = $pool;
        $this->store = $store;

        $this->requiredFileFactory = $requiredFileFactory;
        $this->moduleFactory = $moduleFactory;
        $this->layoutFactory = $layoutFactory;
        $this->dataSetFactory = $dataSetFactory;
        $this->displayFactory = $displayFactory;
        $this->userGroupFactory = $userGroupFactory;
        $this->bandwidthFactory = $bandwidthFactory;
        $this->mediaFactory = $mediaFactory;
        $this->widgetFactory = $widgetFactory;
        $this->regionFactory = $regionFactory;
        $this->notificationFactory = $notificationFactory;
        $this->displayEventFactory = $displayEventFactory;
        $this->scheduleFactory = $scheduleFactory;
        $this->dayPartFactory = $dayPartFactory;
    }

    public function SetupSubDependency( $logProcessor)
    {
        //$this->logProcessor = $logProcessor;
    }

    protected function returnAsJson()
    {
        $this->app->response()->header('Content-Type', 'application/json');
        $this->app->response()->body($this->getState()->asJson());
        $this->app->stop();        
    }
    /**
     * Get Cache Pool
     * @return \Stash\Interfaces\PoolInterface
     */
    protected function getPool()
    {
        return $this->pool;
    }

    /**
     * Get Store
     * @return StorageServiceInterface
     */
    protected function getStore()
    {
        return $this->store;
    }

    /**
     * Get Required Files (common)
     * @param $serverKey
     * @param $hardwareKey
     * @param bool $httpDownloads
     * @return string
     * @throws \Xibo\Exception\XMDSFault
     */
    protected function doRequiredFiles($serverKey, $hardwareKey, $httpDownloads)
    {
        //$this->logProcessor->setRoute('RequiredFiles');

        // Sanitize
        $serverKey = $this->getSanitizer()->string($serverKey);
        $hardwareKey = $this->getSanitizer()->string($hardwareKey);
        $rfLookAhead = $this->getSanitizer()->int($this->getConfig()->GetSetting('REQUIRED_FILES_LOOKAHEAD'));

        // Check the serverKey matches
        if ($serverKey != $this->getConfig()->GetSetting('SERVER_KEY'))
            throw new \Xibo\Exception\XMDSFault('Sender', 'The Server key you entered does not match with the server key at this address');

        // Make sure we are sticking to our bandwidth limit
        if (!$this->checkBandwidth())
            throw new \Xibo\Exception\XMDSFault('Receiver', "Bandwidth Limit exceeded");

        $libraryLocation = $this->getConfig()->GetSetting("LIBRARY_LOCATION");

        // auth this request...
        if (!$this->authDisplay($hardwareKey))
            throw new \Xibo\Exception\XMDSFault('Sender', 'This display is not licensed.');

        // Check the cache
        $cache = $this->getPool()->getItem($this->display->getCacheKey() . '/requiredFiles');

        $output = $cache->get();

        // Required Files caching operates in lockstep with nonce caching
        //  - required files are cached for 4 hours
        //  - nonces have an expiry of 1 day
        //  - nonces are marked "used" when they get used
        //  - nonce use/expiry is not checked for XMDS served files (getfile, getresource)
        //  - nonce use/expiry is checked for HTTP served files (media, layouts)
        //  - Each time a nonce is used through HTTP, the required files cache is invalidated so that new nonces
        //    are generated for the next request.
        if ($cache->isHit()) {
            $this->getLog()->info('Returning required files from Cache for display %s', $this->display->display);

            // Log Bandwidth
            $this->logBandwidth($this->display->displayId, Bandwidth::$RF, strlen($output));

            return $output;
        }

        // Generate a new nonce for this player and store it in the cache.
        $playerNonce = Random::generateString(32);
        $playerNonceCache = $this->pool->getItem('/display/nonce/' . $this->display->displayId);
        $playerNonceCache->set($playerNonce);
        $this->pool->saveDeferred($playerNonceCache);

        // Get all required files for this display.
        // we will use this to drop items from the requirefile table if they are no longer in required files
        $rfIds = array_map(function ($element) {
            return intval($element['rfId']);
        }, $this->getStore()->select('SELECT rfId FROM `requiredfile` WHERE displayId = :displayId', ['displayId' => $this->display->displayId]));
        $newRfIds = [];

        // Build a new RF
        $requiredFilesXml = new \DOMDocument("1.0");
        $fileElements = $requiredFilesXml->createElement("files");
        $requiredFilesXml->appendChild($fileElements);

        // Hour to hour time bands for the query
        // Start at the current hour
        $fromFilter = $this->getDate()->parse()->setTime(0, 0, 0);

        if ($this->getConfig()->GetSetting('SCHEDULE_LOOKAHEAD') == 'On')
            $toFilter = $fromFilter->copy()->addSeconds($rfLookAhead);
        else
            $toFilter = $fromFilter->copy()->addHour();

        $this->getLog()->debug(sprintf('FromDT = %s. ToDt = %s', $fromFilter->toRssString(), $toFilter->toRssString()));

        try {
            $dbh = $this->getStore()->getConnection();

            // Get a list of all layout ids in the schedule right now
            // including any layouts that have been associated to our Display Group
            $SQL = '
                SELECT layout.layoutID, 
                    schedule.DisplayOrder, 
                    lkcampaignlayout.DisplayOrder AS LayoutDisplayOrder, 
                    schedule.eventId, 
                    schedule.fromDt, 
                    schedule.toDt, 
                    schedule.recurrence_type AS recurrenceType,
                    schedule.recurrence_detail AS recurrenceDetail,
                    schedule.recurrence_range AS recurrenceRange,
                    schedule.recurrenceRepeatsOn,
                    schedule.lastRecurrenceWatermark,
                    schedule.dayPartId
                  FROM `campaign`
                    INNER JOIN `schedule`
                    ON `schedule`.CampaignID = campaign.CampaignID
                    INNER JOIN `lkscheduledisplaygroup`
                    ON `lkscheduledisplaygroup`.eventId = `schedule`.eventId
                    INNER JOIN `lkcampaignlayout`
                    ON lkcampaignlayout.CampaignID = campaign.CampaignID
                    INNER JOIN `layout`
                    ON lkcampaignlayout.LayoutID = layout.LayoutID
                    INNER JOIN `lkdgdg`
                    ON `lkdgdg`.parentId = `lkscheduledisplaygroup`.displayGroupId
                    INNER JOIN `lkdisplaydg`
                    ON lkdisplaydg.DisplayGroupID = `lkdgdg`.childId
                 WHERE lkdisplaydg.DisplayID = :displayId
                    AND (
                      (schedule.FromDT < :toDt AND IFNULL(`schedule`.toDt, `schedule`.fromDt) > :fromDt) 
                      OR `schedule`.recurrence_range >= :fromDt 
                      OR (
                        IFNULL(`schedule`.recurrence_range, 0) = 0 AND IFNULL(`schedule`.recurrence_type, \'\') <> \'\' 
                      )
                    )
                    AND layout.retired = 0
                UNION
                SELECT `lklayoutdisplaygroup`.layoutId, 
                    0 AS DisplayOrder, 
                    0 AS LayoutDisplayOrder, 
                    0 AS eventId, 
                    0 AS fromDt, 
                    0 AS toDt, 
                    NULL AS recurrenceType, 
                    NULL AS recurrenceDetail,
                    NULL AS recurrenceRange,
                    NULL AS recurrenceRepeatsOn,
                    NULL AS lastRecurrenceWatermark,
                    NULL AS dayPartId
                  FROM `lklayoutdisplaygroup`
                    INNER JOIN `lkdgdg`
                    ON `lkdgdg`.parentId = `lklayoutdisplaygroup`.displayGroupId
                    INNER JOIN `lkdisplaydg`
                    ON lkdisplaydg.DisplayGroupID = `lkdgdg`.childId
                    INNER JOIN `layout`
                    ON `layout`.layoutID = `lklayoutdisplaygroup`.layoutId
                 WHERE lkdisplaydg.DisplayID = :displayId
                ORDER BY DisplayOrder, LayoutDisplayOrder, eventId
            ';

            $params = array(
                'displayId' => $this->display->displayId,
                'fromDt' => $fromFilter->format('U'),
                'toDt' => $toFilter->format('U')
            );

            if ($this->display->isAuditing())
                $this->getLog()->sql($SQL, $params);

            $sth = $dbh->prepare($SQL);
            $sth->execute($params);

            // Our layout list will always include the default layout
            $layouts = array();
            $layouts[] = $this->display->defaultLayoutId;

            // Build up the other layouts into an array
            foreach ($sth->fetchAll() as $row) {
                $layoutId = $this->getSanitizer()->int($row['layoutID']);

                if ($row['eventId'] != 0) {
                    $schedule = $this->scheduleFactory->createEmpty()->hydrate($row);
                    $schedule
                        ->setDateService($this->getDate())
                        ->setDayPartFactory($this->dayPartFactory);
                    $scheduleEvents = $schedule->getEvents($fromFilter, $toFilter);

                    if (count($scheduleEvents) <= 0)
                        continue;
                }

                $layouts[] = $layoutId;
            }

        } catch (\Exception $e) {
            $this->getLog()->error('Unable to get a list of layouts. ' . $e->getMessage());
            return new \Xibo\Exception\XMDSFault('Sender', 'Unable to get a list of layouts');
        }

        // Create a comma separated list to pass into the query which gets file nodes
        $layoutIdList = implode(',', $layouts);

        try {
            $dbh = $this->getStore()->getConnection();

            // Run a query to get all required files for this display.
            // Include the following:
            // DownloadOrder:
            //  1 - Module System Files and fonts
            //  2 - Media Linked to Displays
            //  3 - Media Linked to Widgets in the Scheduled Layouts
            //  4 - Background Images for all Scheduled Layouts
            $SQL = "
                SELECT 1 AS DownloadOrder, storedAs AS path, media.mediaID AS id, media.`MD5`, media.FileSize
                   FROM `media`
                 WHERE media.type = 'font'
                    OR (media.type = 'module' AND media.moduleSystemFile = 1)
                UNION ALL
                SELECT 2 AS DownloadOrder, storedAs AS path, media.mediaID AS id, media.`MD5`, media.FileSize
                   FROM `media`
                    INNER JOIN `lkmediadisplaygroup`
                    ON lkmediadisplaygroup.mediaid = media.MediaID
                    INNER JOIN `lkdgdg`
                    ON `lkdgdg`.parentId = `lkmediadisplaygroup`.displayGroupId
                    INNER JOIN `lkdisplaydg`
                    ON lkdisplaydg.DisplayGroupID = `lkdgdg`.childId
                 WHERE lkdisplaydg.DisplayID = :displayId
                UNION ALL
                SELECT 3 AS DownloadOrder, storedAs AS path, media.mediaID AS id, media.`MD5`, media.FileSize
                  FROM media
                   INNER JOIN `lkwidgetmedia`
                   ON `lkwidgetmedia`.mediaID = media.MediaID
                   INNER JOIN `widget`
                   ON `widget`.widgetId = `lkwidgetmedia`.widgetId
                   INNER JOIN `lkregionplaylist`
                   ON `lkregionplaylist`.playlistId = `widget`.playlistId
                   INNER JOIN `region`
                   ON `region`.regionId = `lkregionplaylist`.regionId
                   INNER JOIN layout
                   ON layout.LayoutID = region.layoutId
                 WHERE layout.layoutId IN (%s)
                UNION ALL
                SELECT 4 AS DownloadOrder, storedAs AS path, media.mediaId AS id, media.`MD5`, media.FileSize
                  FROM `media`
                 WHERE `media`.mediaID IN (
                    SELECT backgroundImageId
                      FROM `layout`
                     WHERE layoutId IN (%s)
                 )
                ORDER BY DownloadOrder
            ";

            $sth = $dbh->prepare(sprintf($SQL, $layoutIdList, $layoutIdList));
            $sth->execute(array(
                'displayId' => $this->display->displayId
            ));

            // Prepare a SQL statement in case we need to update the MD5 and FileSize on media nodes.
            $mediaSth = $dbh->prepare('UPDATE media SET `MD5` = :md5, FileSize = :size WHERE MediaID = :mediaid');

            // Keep a list of path names added to RF to prevent duplicates
            $pathsAdded = array();

            foreach ($sth->fetchAll() as $row) {
                // Media
                $path = $this->getSanitizer()->string($row['path']);
                $id = $this->getSanitizer()->string($row['id']);
                $md5 = $row['MD5'];
                $fileSize = $this->getSanitizer()->int($row['FileSize']);

                // Check we haven't added this before
                if (in_array($path, $pathsAdded))
                    continue;

                // Do we need to calculate a new MD5?
                // If they are empty calculate them and save them back to the media.
                if ($md5 == '' || $fileSize == 0) {

                    $md5 = md5_file($libraryLocation . $path);
                    $fileSize = filesize($libraryLocation . $path);

                    // Update the media record with this information
                    $mediaSth->execute(array('md5' => $md5, 'size' => $fileSize, 'mediaid' => $id));
                }

                // Add nonce
                $mediaNonce = $this->requiredFileFactory->createForMedia($this->display->displayId, $id, $fileSize, $path)->save();
                $newRfIds[] = $mediaNonce->rfId;

                // Add the file node
                $file = $requiredFilesXml->createElement("file");
                $file->setAttribute("type", 'media');
                $file->setAttribute("id", $id);
                $file->setAttribute("size", $fileSize);
                $file->setAttribute("md5", $md5);

                if ($httpDownloads) {
                    // Serve a link instead (standard HTTP link)
                    $file->setAttribute("path", $this->generateRequiredFileDownloadPath('M', $id, $playerNonce));
                    $file->setAttribute("saveAs", $path);
                    $file->setAttribute("download", 'http');
                }
                else {
                    $file->setAttribute("download", 'xmds');
                    $file->setAttribute("path", $path);
                }

                $fileElements->appendChild($file);

                // Add to paths added
                $pathsAdded[] = $path;
            }
        } catch (\Exception $e) {
            $this->getLog()->error('Unable to get a list of required files. ' . $e->getMessage());
            $this->getLog()->debug($e->getTraceAsString());
            return new \Xibo\Exception\XMDSFault('Sender', 'Unable to get a list of files');
        }

        // Get an array of modules to use
        $modules = $this->moduleFactory->get();

        // Reset the paths added array to start again with layouts
        $pathsAdded = [];

        // Go through each layout and see if we need to supply any resource nodes.
        foreach ($layouts as $layoutId) {

            // Check we haven't added this before
            if (in_array($layoutId, $pathsAdded))
                continue;

            // Load this layout
            try {
                $layout = $this->layoutFactory->loadById($layoutId);
                $layout->loadPlaylists();
            } catch (NotFoundException $e) {
                $this->getLog()->error('Layout not found - ID: ' . $layoutId . ', skipping.');
                continue;
            }

            // Make sure its XLF is up to date
            $path = $layout->xlfToDisk(['notify' => false]);

            // For layouts the MD5 column is the layout xml
            $fileSize = filesize($path);
            $md5 = md5_file($path);
            $fileName = basename($path);

            // Log
            if ($this->display->isAuditing())
                $this->getLog()->debug('MD5 for layoutid ' . $layoutId . ' is: [' . $md5 . ']');

            // Add nonce
            $layoutNonce = $this->requiredFileFactory->createForLayout($this->display->displayId, $layoutId, $fileSize, $fileName)->save();
            $newRfIds[] = $layoutNonce->rfId;

            // Add the Layout file element
            $file = $requiredFilesXml->createElement("file");
            $file->setAttribute("type", 'layout');
            $file->setAttribute("id", $layoutId);
            $file->setAttribute("size", $fileSize);
            $file->setAttribute("md5", $md5);

            $supportsHttpLayouts = ($this->display->clientType == 'android' || ($this->display->clientType == 'windows' && $this->display->clientCode > 120));

            if ($httpDownloads && $supportsHttpLayouts) {
                // Serve a link instead (standard HTTP link)
                $file->setAttribute("path", $this->generateRequiredFileDownloadPath('L', $layoutId, $playerNonce));
                $file->setAttribute("saveAs", $fileName);
                $file->setAttribute("download", 'http');
            }
            else {
                $file->setAttribute("download", 'xmds');
                $file->setAttribute("path", $layoutId);
            }

            $fileElements->appendChild($file);

            // Get the Layout Modified Date
            $layoutModifiedDt = new \DateTime($layout->modifiedDt);

            // Load the layout XML and work out if we have any ticker / text / dataset media items
            foreach ($layout->regions as $region) {
                /* @var Region $region */
                foreach ($region->playlists as $playlist) {
                    /* @var Playlist $playlist */
                    foreach ($playlist->widgets as $widget) {
                        /* @var Widget $widget */
                        if ($widget->type == 'ticker' ||
                            $widget->type == 'text' ||
                            $widget->type == 'datasetview' ||
                            $widget->type == 'webpage' ||
                            $widget->type == 'embedded' ||
                            $modules[$widget->type]->renderAs == 'html'
                        ) {
                            // Add nonce
                            $getResourceRf = $this->requiredFileFactory->createForGetResource($this->display->displayId, $widget->widgetId)->save();
                            $newRfIds[] = $getResourceRf->rfId;

                            // Does the media provide a modified Date?
                            $widgetModifiedDt = $layoutModifiedDt->getTimestamp();

                            if ($widget->type == 'datasetview' || $widget->type == 'ticker') {
                                try {
                                    $dataSetId = $widget->getOption('dataSetId');
                                    $dataSet = $this->dataSetFactory->getById($dataSetId);
                                    $widgetModifiedDt = $dataSet->lastDataEdit;
                                }
                                catch (NotFoundException $e) {
                                    // Widget doesn't have a dataSet associated to it
                                    // This is perfectly valid, so ignore it.
                                }
                            }

                            // Append this item to required files
                            $file = $requiredFilesXml->createElement("file");
                            $file->setAttribute('type', 'resource');
                            $file->setAttribute('id', $widget->widgetId);
                            $file->setAttribute('layoutid', $layoutId);
                            $file->setAttribute('regionid', $region->regionId);
                            $file->setAttribute('mediaid', $widget->widgetId);
                            $file->setAttribute('updated', $widgetModifiedDt);
                            $fileElements->appendChild($file);
                        }
                    }
                }
            }

            // Add to paths added
            $pathsAdded[] = $layoutId;
        }

        // Add a blacklist node
        $blackList = $requiredFilesXml->createElement("file");
        $blackList->setAttribute("type", "blacklist");

        $fileElements->appendChild($blackList);

        try {
            $dbh = $this->getStore()->getConnection();

            $sth = $dbh->prepare('SELECT MediaID FROM blacklist WHERE DisplayID = :displayid AND isIgnored = 0');
            $sth->execute(array(
                'displayid' => $this->display->displayId
            ));

            // Add a black list element for each file
            foreach ($sth->fetchAll() as $row) {
                $file = $requiredFilesXml->createElement("file");
                $file->setAttribute("id", $row['MediaID']);

                $blackList->appendChild($file);
            }
        } catch (\Exception $e) {
            $this->getLog()->error('Unable to get a list of blacklisted files. ' . $e->getMessage());
            return new \Xibo\Exception\XMDSFault('Sender', 'Unable to get a list of blacklisted files');
        }

        // Remove any required files that remain in the array of rfIds
        $rfIds = array_values(array_diff($rfIds, $newRfIds));
        if (count($rfIds) > 0) {
            $this->getLog()->debug('Removing ' . count($rfIds) . ' from requiredfiles');

            try {
                $this->getStore()->updateWithDeadlockLoop('DELETE FROM `requiredfile` WHERE rfId IN (' . implode(',', array_fill(0, count($rfIds), '?')) . ')', $rfIds);
            } catch (DeadlockException $deadlockException) {
                $this->getLog()->error('Deadlock when deleting required files - ignoring and continuing with request');
            }
        }

        // Phone Home?
        $this->phoneHome();

        if ($this->display->isAuditing())
            $this->getLog()->debug($requiredFilesXml->saveXML());

        // Return the results of requiredFiles()
        $requiredFilesXml->formatOutput = true;
        $output = $requiredFilesXml->saveXML();

        // Cache
        $cache->set($output);

        // RF cache expires every 4 hours
        $cache->expiresAfter(3600*4);
        $this->getPool()->saveDeferred($cache);

        // Log Bandwidth
        $this->logBandwidth($this->display->displayId, Bandwidth::$RF, strlen($output));

        return $output;
    }

    /**
     * @param $serverKey
     * @param $hardwareKey
     * @param array $options
     * @return mixed
     * @throws \Xibo\Exception\XMDSFault
     */
    protected function doSchedule($serverKey, $hardwareKey, $options = [])
    {
        //$this->logProcessor->setRoute('Schedule');

        $options = array_merge(['dependentsAsNodes' => false, 'includeOverlays' => false], $options);

        // Sanitize
        $serverKey = $this->getSanitizer()->string($serverKey);
        $hardwareKey = $this->getSanitizer()->string($hardwareKey);
        $rfLookAhead = $this->getSanitizer()->int($this->getConfig()->GetSetting('REQUIRED_FILES_LOOKAHEAD'));

        // Check the serverKey matches
        if ($serverKey != $this->getConfig()->GetSetting('SERVER_KEY'))
            throw new \Xibo\Exception\XMDSFault('Sender', 'The Server key you entered does not match with the server key at this address');

        // Make sure we are sticking to our bandwidth limit
        if (!$this->checkBandwidth())
            throw new \Xibo\Exception\XMDSFault('Receiver', "Bandwidth Limit exceeded");

        // auth this request...
        if (!$this->authDisplay($hardwareKey))
            throw new \Xibo\Exception\XMDSFault('Sender', "This display client is not licensed");

        // Check the cache
        $cache = $this->getPool()->getItem($this->display->getCacheKey() . '/schedule');

        $output = $cache->get();

        if ($cache->isHit()) {
            $this->getLog()->info('Returning Schedule from Cache for display %s. Options %s.', $this->display->display, json_encode($options));

            // Log Bandwidth
            $this->logBandwidth($this->display->displayId, Bandwidth::$SCHEDULE, strlen($output));

            return $output;
        }

        // Generate the Schedule XML
        $scheduleXml = new \DOMDocument("1.0");
        $layoutElements = $scheduleXml->createElement("schedule");

        $scheduleXml->appendChild($layoutElements);

        // Hour to hour time bands for the query
        // Start at the current hour
        $fromFilter = $this->getDate()->parse()->setTime(0, 0, 0);

        if ($this->getConfig()->GetSetting('SCHEDULE_LOOKAHEAD') == 'On')
            $toFilter = $fromFilter->copy()->addSeconds($rfLookAhead);
        else
            $toFilter = $fromFilter->copy()->addHour();

        $this->getLog()->debug(sprintf('FromDT = %s. ToDt = %s', $fromFilter->toRssString(), $toFilter->toRssString()));

        try {
            $dbh = $this->getStore()->getConnection();

            // Get all the module dependants
            $sth = $dbh->prepare("SELECT DISTINCT StoredAs FROM `media` WHERE media.type = 'font' OR (media.type = 'module' AND media.moduleSystemFile = 1) ");
            $sth->execute(array());
            $rows = $sth->fetchAll();
            $moduleDependents = array();

            foreach ($rows as $dependent) {
                $moduleDependents[] = $dependent['StoredAs'];
            }

            // Add file nodes to the $fileElements
            // Firstly get all the scheduled layouts
            $SQL = '
                SELECT `schedule`.eventTypeId, 
                    layout.layoutId, 
                    `layout`.status, 
                    `command`.code, 
                    schedule.fromDt, 
                    schedule.toDt,
                    schedule.recurrence_type AS recurrenceType,
                    schedule.recurrence_detail AS recurrenceDetail,
                    schedule.recurrence_range AS recurrenceRange,
                    schedule.recurrenceRepeatsOn,
                    schedule.lastRecurrenceWatermark,
                    schedule.eventId, 
                    schedule.is_priority,
                    schedule.dayPartId
            ';

            if (!$options['dependentsAsNodes']) {
                // Pull in the dependents using GROUP_CONCAT
                $SQL .= ' ,
                  (
                    SELECT GROUP_CONCAT(DISTINCT StoredAs)
                      FROM `media`
                        INNER JOIN `lkwidgetmedia`
                        ON `lkwidgetmedia`.MediaID = `media`.MediaID
                        INNER JOIN `widget`
                        ON `widget`.widgetId = `lkwidgetmedia`.widgetId
                        INNER JOIN `lkregionplaylist`
                        ON `lkregionplaylist`.playlistId = `widget`.playlistId
                        INNER JOIN `region`
                        ON `region`.regionId = `lkregionplaylist`.regionId
                     WHERE `region`.layoutId = `layout`.layoutId
                      AND media.type <> \'module\'
                    GROUP BY `region`.layoutId
                  ) AS Dependents
                ';
            }

            $SQL .= '
                   FROM `schedule`
                    INNER JOIN `lkscheduledisplaygroup`
                    ON `lkscheduledisplaygroup`.eventId = `schedule`.eventId
                    INNER JOIN `lkdgdg`
                    ON `lkdgdg`.parentId = `lkscheduledisplaygroup`.displayGroupId
                    INNER JOIN `lkdisplaydg`
                    ON lkdisplaydg.DisplayGroupID = `lkdgdg`.childId
                    LEFT OUTER JOIN `campaign`
                    ON `schedule`.CampaignID = campaign.CampaignID
                    LEFT OUTER JOIN `lkcampaignlayout`
                    ON lkcampaignlayout.CampaignID = campaign.CampaignID
                    LEFT OUTER JOIN `layout`
                    ON lkcampaignlayout.LayoutID = layout.LayoutID
                      AND layout.retired = 0
                    LEFT OUTER JOIN `command`
                    ON `command`.commandId = `schedule`.commandId
                 WHERE lkdisplaydg.DisplayID = :displayId
                    AND (
                      (schedule.FromDT < :toDt AND IFNULL(`schedule`.toDt, `schedule`.fromDt) > :fromDt) 
                      OR `schedule`.recurrence_range >= :fromDt OR (
                        IFNULL(`schedule`.recurrence_range, 0) = 0 AND IFNULL(`schedule`.recurrence_type, \'\') <> \'\' 
                        )
                    )
                ORDER BY schedule.DisplayOrder, IFNULL(lkcampaignlayout.DisplayOrder, 0), schedule.FromDT
            ';

            $params = array(
                'displayId' => $this->display->displayId,
                'toDt' => $toFilter->format('U'),
                'fromDt' => $fromFilter->format('U')
            );

            if ($this->display->isAuditing())
                $this->getLog()->sql($SQL, $params);

            $sth = $dbh->prepare($SQL);
            $sth->execute($params);

            $events = $sth->fetchAll(\PDO::FETCH_ASSOC);

            // If our dependents are nodes, then build a list of layouts we can use to query for nodes
            $layoutDependents = [];

            if ($options['dependentsAsNodes']) {

                // Layouts (pop in the default)
                $layoutIds = [$this->display->defaultLayoutId];

                foreach ($events as $event) {
                    if ($event['layoutId'] != null && !in_array($event['layoutId'], $layoutIds))
                        $layoutIds[] = $event['layoutId'];
                }

                $SQL = '
                    SELECT DISTINCT `region`.layoutId, `media`.storedAs
                      FROM `media`
                        INNER JOIN `lkwidgetmedia`
                        ON `lkwidgetmedia`.MediaID = `media`.MediaID
                        INNER JOIN `widget`
                        ON `widget`.widgetId = `lkwidgetmedia`.widgetId
                        INNER JOIN `lkregionplaylist`
                        ON `lkregionplaylist`.playlistId = `widget`.playlistId
                        INNER JOIN `region`
                        ON `region`.regionId = `lkregionplaylist`.regionId
                     WHERE `region`.layoutId IN (' . implode(',', $layoutIds) . ')
                      AND media.type <> \'module\'
                ';

                foreach ($this->getStore()->select($SQL, []) as $row) {
                    if (!array_key_exists($row['layoutId'], $layoutDependents))
                        $layoutDependents[$row['layoutId']] = [];

                    $layoutDependents[$row['layoutId']][] = $row['storedAs'];
                }

                $this->getLog()->debug('Resolved dependents for Schedule: %s.', json_encode($layoutDependents, JSON_PRETTY_PRINT));
            }

            $overlayNodes = null;

            // We must have some results in here by this point
            foreach ($events as $row) {
                $eventTypeId = $row['eventTypeId'];
                $layoutId = $row['layoutId'];
                $commandCode = $row['code'];
                $fromDt = date('Y-m-d H:i:s', $row['fromDt']);
                $toDt = date('Y-m-d H:i:s', $row['toDt']);
                $scheduleId = $row['eventId'];
                $is_priority = $this->getSanitizer()->int($row['is_priority']);

                $schedule = $this->scheduleFactory->createEmpty()->hydrate($row);
                $schedule
                    ->setDateService($this->getDate())
                    ->setDayPartFactory($this->dayPartFactory);
                $scheduleEvents = $schedule->getEvents($fromFilter, $toFilter);

                $this->getLog()->debug(count($scheduleEvents) . ' events for eventId ' . $schedule->eventId);

                foreach ($scheduleEvents as $scheduleEvent) {

                    $eventTypeId = $row['eventTypeId'];
                    $layoutId = $row['layoutId'];
                    $commandCode = $row['code'];
                    $fromDt = $this->getDate()->getLocalDate($scheduleEvent->fromDt);
                    $toDt = $this->getDate()->getLocalDate($scheduleEvent->toDt);
                    $scheduleId = $row['eventId'];
                    $is_priority = $this->getSanitizer()->int($row['is_priority']);

                    if ($eventTypeId == Schedule::$LAYOUT_EVENT) {
                        // Ensure we have a layoutId (we may not if an empty campaign is assigned)
                        // https://github.com/xibosignage/xibo/issues/894
                        if ($layoutId == 0 || empty($layoutId)) {
                            $this->getLog()->info('Player has empty event scheduled. Display = %s, EventId = %d', $this->display->display, $scheduleId);
                            continue;
                        }

                        // Check the layout status
                        // https://github.com/xibosignage/xibo/issues/743
                        if (intval($row['status']) > 3) {
                            $this->getLog()->info('Player has invalid layout scheduled. Display = %s, LayoutId = %d', $this->display->display, $layoutId);
                            continue;
                        }

                        // Add a layout node to the schedule
                        $layout = $scheduleXml->createElement("layout");
                        $layout->setAttribute("file", $layoutId);
                        $layout->setAttribute("fromdt", $fromDt);
                        $layout->setAttribute("todt", $toDt);
                        $layout->setAttribute("scheduleid", $scheduleId);
                        $layout->setAttribute("priority", $is_priority);

                        if (!$options['dependentsAsNodes']) {
                            $dependents = $this->getSanitizer()->string($row['Dependents']);
                            $layout->setAttribute("dependents", $dependents);
                        } else if (array_key_exists($layoutId, $layoutDependents)) {
                            $dependentNode = $scheduleXml->createElement("dependents");

                            foreach ($layoutDependents[$layoutId] as $storedAs) {
                                $fileNode = $scheduleXml->createElement("file", $storedAs);

                                $dependentNode->appendChild($fileNode);
                            }

                            $layout->appendChild($dependentNode);
                        }

                        $layoutElements->appendChild($layout);

                    } else if ($eventTypeId == Schedule::$COMMAND_EVENT) {
                        // Add a command node to the schedule
                        $command = $scheduleXml->createElement("command");
                        $command->setAttribute("date", $fromDt);
                        $command->setAttribute("scheduleid", $scheduleId);
                        $command->setAttribute('code', $commandCode);
                        $layoutElements->appendChild($command);
                    } else if ($eventTypeId == Schedule::$OVERLAY_EVENT && $options['includeOverlays']) {
                        // Ensure we have a layoutId (we may not if an empty campaign is assigned)
                        // https://github.com/xibosignage/xibo/issues/894
                        if ($layoutId == 0 || empty($layoutId)) {
                            $this->getLog()->error('Player has empty event scheduled. Display = %s, EventId = %d', $this->display->display, $scheduleId);
                            continue;
                        }

                        // Check the layout status
                        // https://github.com/xibosignage/xibo/issues/743
                        if (intval($row['status']) > 3) {
                            $this->getLog()->error('Player has invalid layout scheduled. Display = %s, LayoutId = %d', $this->display->display, $layoutId);
                            continue;
                        }

                        if ($overlayNodes == null) {
                            $overlayNodes = $scheduleXml->createElement('overlays');
                        }

                        $overlay = $scheduleXml->createElement('overlay');
                        $overlay->setAttribute("file", $layoutId);
                        $overlay->setAttribute("fromdt", $fromDt);
                        $overlay->setAttribute("todt", $toDt);
                        $overlay->setAttribute("scheduleid", $scheduleId);
                        $overlay->setAttribute("priority", $is_priority);

                        // Add to the overlays node list
                        $overlayNodes->appendChild($overlay);
                    }
                }
            }

            // Add the overlay nodes if we had any
            if ($overlayNodes != null)
                $layoutElements->appendChild($overlayNodes);

        } catch (\Exception $e) {
            $this->getLog()->error('Error getting a list of layouts for the schedule. ' . $e->getMessage());
            return new \Xibo\Exception\XMDSFault('Sender', 'Unable to get A list of layouts for the schedule');
        }

        // Are we interleaving the default?
        if ($this->display->incSchedule == 1) {
            // Add as a node at the end of the schedule.
            $layout = $scheduleXml->createElement("layout");

            $layout->setAttribute("file", $this->display->defaultLayoutId);
            $layout->setAttribute("fromdt", '2000-01-01 00:00:00');
            $layout->setAttribute("todt", '2030-01-19 00:00:00');
            $layout->setAttribute("scheduleid", 0);
            $layout->setAttribute("priority", 0);

            if ($options['dependentsAsNodes'] && array_key_exists($this->display->defaultLayoutId, $layoutDependents)) {
                $dependentNode = $scheduleXml->createElement("dependents");

                foreach ($layoutDependents[$this->display->defaultLayoutId] as $storedAs) {
                    $fileNode = $scheduleXml->createElement("file", $storedAs);

                    $dependentNode->appendChild($fileNode);
                }

                $layout->appendChild($dependentNode);
            }

            $layoutElements->appendChild($layout);
        }

        // Add on the default layout node
        $default = $scheduleXml->createElement("default");
        $default->setAttribute("file", $this->display->defaultLayoutId);

        if ($options['dependentsAsNodes'] && array_key_exists($this->display->defaultLayoutId, $layoutDependents)) {
            $dependentNode = $scheduleXml->createElement("dependents");

            foreach ($layoutDependents[$this->display->defaultLayoutId] as $storedAs) {
                $fileNode = $scheduleXml->createElement("file", $storedAs);

                $dependentNode->appendChild($fileNode);
            }

            $default->appendChild($dependentNode);
        }

        $layoutElements->appendChild($default);

        // Add on a list of global dependants
        $globalDependents = $scheduleXml->createElement("dependants");

        foreach ($moduleDependents as $dep) {
            $dependent = $scheduleXml->createElement("file", $dep);
            $globalDependents->appendChild($dependent);
        }
        $layoutElements->appendChild($globalDependents);

        // Format the output
        $scheduleXml->formatOutput = true;

        if ($this->display->isAuditing())
            $this->getLog()->debug($scheduleXml->saveXML());

        $output = $scheduleXml->saveXML();

        // Cache
        $cache->set($output);
        $cache->expiresAt($toFilter);
        $this->getPool()->saveDeferred($cache);

        // Log Bandwidth
        $this->logBandwidth($this->display->displayId, Bandwidth::$SCHEDULE, strlen($output));

        return $output;
    }

    /**
     * @param $serverKey
     * @param $hardwareKey
     * @param $mediaId
     * @param $type
     * @param $reason
     * @return bool|\Xibo\Exception\XMDSFault
     * @throws \Xibo\Exception\XMDSFault
     */
    protected function doBlackList($serverKey, $hardwareKey, $mediaId, $type, $reason)
    {
        //$this->logProcessor->setRoute('BlackList');

        // Sanitize
        $serverKey = $this->getSanitizer()->string($serverKey);
        $hardwareKey = $this->getSanitizer()->string($hardwareKey);
        $mediaId = $this->getSanitizer()->string($mediaId);
        $type = $this->getSanitizer()->string($type);
        $reason = $this->getSanitizer()->string($reason);

        // Check the serverKey matches
        if ($serverKey != $this->getConfig()->GetSetting('SERVER_KEY'))
            throw new \Xibo\Exception\XMDSFault('Sender', 'The Server key you entered does not match with the server key at this address');

        // Make sure we are sticking to our bandwidth limit
        if (!$this->checkBandwidth())
            throw new \Xibo\Exception\XMDSFault('Receiver', "Bandwidth Limit exceeded");

        // Authenticate this request...
        if (!$this->authDisplay($hardwareKey))
            throw new \Xibo\Exception\XMDSFault('Receiver', "This display client is not licensed", $hardwareKey);

        if ($this->display->isAuditing())
            $this->getLog()->debug('Blacklisting ' . $mediaId . ' for ' . $reason);

        try {
            $dbh = $this->getStore()->getConnection();

            // Check to see if this media / display is already blacklisted (and not ignored)
            $sth = $dbh->prepare('SELECT BlackListID FROM blacklist WHERE MediaID = :mediaid AND isIgnored = 0 AND DisplayID = :displayid');
            $sth->execute(array(
                'mediaid' => $mediaId,
                'displayid' => $this->display->displayId
            ));

            $results = $sth->fetchAll();

            if (count($results) == 0) {

                $insertSth = $dbh->prepare('
                        INSERT INTO blacklist (MediaID, DisplayID, ReportingDisplayID, Reason)
                            VALUES (:mediaid, :displayid, :reportingdisplayid, :reason)
                    ');

                // Insert the black list record
                if ($type == BLACKLIST_SINGLE) {
                    $insertSth->execute(array(
                        'mediaid' => $mediaId,
                        'displayid' => $this->display->displayId,
                        'reportingdisplayid' => $this->display->displayId,
                        'reason' => $reason
                    ));
                } else {
                    $displaySth = $dbh->prepare('SELECT displayID FROM `display`');
                    $displaySth->execute();

                    foreach ($displaySth->fetchAll() as $row) {

                        $insertSth->execute(array(
                            'mediaid' => $mediaId,
                            'displayid' => $row['displayID'],
                            'reportingdisplayid' => $this->display->displayId,
                            'reason' => $reason
                        ));
                    }
                }
            } else {
                if ($this->display->isAuditing())
                    $this->getLog()->debug($mediaId . ' already black listed');
            }
        } catch (\Exception $e) {
            $this->getLog()->error('Unable to query for Blacklist records. ' . $e->getMessage());
            return new \Xibo\Exception\XMDSFault('Sender', "Unable to query for BlackList records.");
        }

        $this->logBandwidth($this->display->displayId, Bandwidth::$BLACKLIST, strlen($reason));

        return true;
    }

    /**
     * @param $serverKey
     * @param $hardwareKey
     * @param $logXml
     * @return bool
     * @throws \Xibo\Exception\XMDSFault
     */
    protected function doSubmitLog($serverKey, $hardwareKey, $logXml)
    {
        //$this->logProcessor->setRoute('SubmitLog');

        // Sanitize
        $serverKey = $this->getSanitizer()->string($serverKey);
        $hardwareKey = $this->getSanitizer()->string($hardwareKey);

        // Check the serverKey matches
        if ($serverKey != $this->getConfig()->GetSetting('SERVER_KEY'))
            throw new \Xibo\Exception\XMDSFault('Sender', 'The Server key you entered does not match with the server key at this address');

        // Make sure we are sticking to our bandwidth limit
        if (!$this->checkBandwidth())
            throw new \Xibo\Exception\XMDSFault('Receiver', "Bandwidth Limit exceeded");

        // Auth this request...
        if (!$this->authDisplay($hardwareKey))
            throw new \Xibo\Exception\XMDSFault('Sender', 'This display client is not licensed.');

        // Load the XML into a DOMDocument
        $document = new \DOMDocument("1.0");

        if (!$document->loadXML($logXml)) {
            $this->getLog()->error('Malformed XML from Player, this will be discarded. The Raw XML String provided is: ' . $logXml);
            $this->getLog()->debug('XML log: ' . $logXml);
            return true;
        }

        // Current log level
        $logLevel = $this->logProcessor->getLevel();
        $discardedLogs = 0;

        // Get the display timezone to use when adjusting log dates.
        $timeZone = $this->display->getSetting('displayTimeZone', '');
        $defaultTimeZone = $this->getConfig()->GetSetting('defaultTimezone');

        // Store processed logs in an array
        $logs = [];

        foreach ($document->documentElement->childNodes as $node) {
            /* @var \DOMElement $node */
            // Make sure we don't consider any text nodes
            if ($node->nodeType == XML_TEXT_NODE)
                continue;

            // Zero out the common vars
            $scheduleId = "";
            $layoutId = "";
            $mediaId = "";
            $method = '';
            $thread = '';
            $type = '';

            // This will be a bunch of trace nodes
            $message = $node->textContent;

            // Each element should have a category and a date
            $date = $node->getAttribute('date');
            $cat = strtolower($node->getAttribute('category'));

            if ($date == '' || $cat == '') {
                $this->getLog()->error('Log submitted without a date or category attribute');
                continue;
            }

            // Does this meet the current log level?
            if ($cat == 'error') {
                $recordLogLevel = Log::ERROR;
                $levelName = 'ERROR';
            }
            else if ($cat == 'audit') {
                $recordLogLevel = Log::DEBUG;
                $levelName = 'DEBUG';
            }
            else {
                $recordLogLevel = Log::NOTICE;
                $levelName = 'NOTICE';
            }

            if ($recordLogLevel > $logLevel) {
                $discardedLogs++;
                continue;
            }

            // Adjust the date according to the display timezone
            $date = ($timeZone != null) ? Date::createFromFormat('Y-m-d H:i:s', $date, $timeZone)->tz($defaultTimeZone) : Date::createFromFormat('Y-m-d H:i:s', $date);
            $date = $this->getDate()->getLocalDate($date);

            // Get the date and the message (all log types have these)
            foreach ($node->childNodes as $nodeElements) {

                if ($nodeElements->nodeName == "scheduleID") {
                    $scheduleId = $nodeElements->textContent;
                } else if ($nodeElements->nodeName == "layoutID") {
                    $layoutId = $nodeElements->textContent;
                } else if ($nodeElements->nodeName == "mediaID") {
                    $mediaId = $nodeElements->textContent;
                } else if ($nodeElements->nodeName == "type") {
                    $type = $nodeElements->textContent;
                } else if ($nodeElements->nodeName == "method") {
                    $method = $nodeElements->textContent;
                } else if ($nodeElements->nodeName == "message") {
                    $message = $nodeElements->textContent;
                } else if ($nodeElements->nodeName == "thread") {
                    if ($nodeElements->textContent != '')
                        $thread = '[' . $nodeElements->textContent . '] ';
                }
            }

            // If the message is still empty, take the entire node content
            if ($message == '')
                $message = $node->textContent;

            $logs[] = [
                $this->logProcessor->getUid(),
                $date,
                'PLAYER',
                $levelName,
                $thread . $method . $type,
                'POST',
                $message . $scheduleId . $layoutId . $mediaId,
                0,
                $this->display->displayId
            ];
        }

        if (count($logs) > 0) {
            // Insert
            $sql = 'INSERT INTO log (runNo, logdate, channel, type, page, function, message, userid, displayid) VALUES ';
            $placeHolders = '(?, ?, ?, ?, ?, ?, ?, ?, ?)';

            $sql = $sql . implode(', ', array_fill(1, count($logs), $placeHolders));

            // Flatten the array
            $data = [];
            foreach ($logs as $log) {
                foreach ($log as $field) {
                    $data[] = $field;
                }
            }

            // Insert
            $this->getStore()->isolated($sql, $data);
        } else {
            $this->getLog()->error('0 logs resolved from log package');
        }

        if ($discardedLogs > 0)
            $this->getLog()->error('Discarded ' . $discardedLogs . ' logs. Consider adjusting your display profile log level. Resolved level is ' . $logLevel);

        $this->logBandwidth($this->display->displayId, Bandwidth::$SUBMITLOG, strlen($logXml));

        return true;
    }

    /**
     * @param $serverKey
     * @param $hardwareKey
     * @param $statXml
     * @return bool
     * @throws \Xibo\Exception\XMDSFault
     */
    protected function doSubmitStats($serverKey, $hardwareKey, $statXml)
    {
        //$this->logProcessor->setRoute('SubmitStats');

        // Sanitize
        $serverKey = $this->getSanitizer()->string($serverKey);
        $hardwareKey = $this->getSanitizer()->string($hardwareKey);

        // Check the serverKey matches
        if ($serverKey != $this->getConfig()->GetSetting('SERVER_KEY'))
            throw new \Xibo\Exception\XMDSFault('Sender', 'The Server key you entered does not match with the server key at this address');

        // Make sure we are sticking to our bandwidth limit
        if (!$this->checkBandwidth())
            throw new \Xibo\Exception\XMDSFault('Receiver', "Bandwidth Limit exceeded");

        // Auth this request...
        if (!$this->authDisplay($hardwareKey))
            throw new \Xibo\Exception\XMDSFault('Receiver', "This display client is not licensed");

        if ($this->display->isAuditing())
            $this->getLog()->debug('Received XML. ' . $statXml);

        if ($statXml == "")
            throw new \Xibo\Exception\XMDSFault('Receiver', "Stat XML is empty.");

        // Load the XML into a DOMDocument
        $document = new \DOMDocument("1.0");
        $document->loadXML($statXml);

        foreach ($document->documentElement->childNodes as $node) {
            /* @var \DOMElement $node */
            // Make sure we don't consider any text nodes
            if ($node->nodeType == XML_TEXT_NODE)
                continue;

            // Each element should have these attributes
            $fromdt = $node->getAttribute('fromdt');
            $todt = $node->getAttribute('todt');
            $type = $node->getAttribute('type');

            if ($fromdt == '' || $todt == '' || $type == '') {
                $this->getLog()->error('Stat submitted without the fromdt, todt or type attributes.');
                continue;
            }

            $scheduleID = $node->getAttribute('scheduleid');
            $layoutID = $node->getAttribute('layoutid');
            
            // Slightly confusing behaviour here to support old players without introducting a different call in 
            // xmds v=5.
            // MediaId is actually the widgetId (since 1.8) and the mediaId is looked up by this service
            $widgetId = $node->getAttribute('mediaid');
            
            $mediaId = 0;

            // The mediaId (really widgetId) might well be null
            if ($widgetId == 'null' || $widgetId == '')
                $widgetId = 0;

            if ($widgetId > 0) {
                // Lookup the mediaId
                $media = $this->mediaFactory->getByLayoutAndWidget($layoutID, $widgetId);

                if (count($media) <= 0) {
                    // Non-media widget
                    $mediaId = 0;
                } else {
                    $mediaId = $media[0]->mediaId;
                }
            }
            
            $tag = $node->getAttribute('tag');

            if ($tag == 'null')
                $tag = null;

            // Write the stat record with the information we have available to us.
            try {
                $stat = new Stat($this->getStore(), $this->getLog());
                $stat->type = $type;
                $stat->fromDt = $fromdt;
                $stat->toDt = $todt;
                $stat->scheduleId = $scheduleID;
                $stat->displayId = $this->display->displayId;
                $stat->layoutId = $layoutID;
                $stat->mediaId = $mediaId;
                $stat->widgetId = $widgetId;
                $stat->tag = $tag;
                $stat->save();
            }
            catch (\PDOException $e) {
                $this->getLog()->error('Stat Add failed with error: %s', $e->getMessage());
            }
        }

        $this->logBandwidth($this->display->displayId, Bandwidth::$SUBMITSTATS, strlen($statXml));

        return true;
    }

    /**
     * @param $serverKey
     * @param $hardwareKey
     * @param $inventory
     * @return bool
     * @throws \Xibo\Exception\XMDSFault
     */
    protected function doMediaInventory($serverKey, $hardwareKey, $inventory)
    {
        //$this->logProcessor->setRoute('MediaInventory');

        // Sanitize
        $serverKey = $this->getSanitizer()->string($serverKey);
        $hardwareKey = $this->getSanitizer()->string($hardwareKey);

        // Check the serverKey matches
        if ($serverKey != $this->getConfig()->GetSetting('SERVER_KEY'))
            throw new \Xibo\Exception\XMDSFault('Sender', 'The Server key you entered does not match with the server key at this address');

        // Make sure we are sticking to our bandwidth limit
        if (!$this->checkBandwidth())
            throw new \Xibo\Exception\XMDSFault('Receiver', "Bandwidth Limit exceeded");

        // Auth this request...
        if (!$this->authDisplay($hardwareKey))
            throw new \Xibo\Exception\XMDSFault('Receiver', 'This display client is not licensed');

        if ($this->display->isAuditing())
            $this->getLog()->debug($inventory);

        // Check that the $inventory contains something
        if ($inventory == '')
            throw new \Xibo\Exception\XMDSFault('Receiver', 'Inventory Cannot be Empty');

        // Load the XML into a DOMDocument
        $document = new \DOMDocument("1.0");
        $document->loadXML($inventory);

        // Assume we are complete (but we are getting some)
        $mediaInventoryComplete = 1;

        $xpath = new \DOMXPath($document);
        $fileNodes = $xpath->query("//file");

        foreach ($fileNodes as $node) {
            /* @var \DOMElement $node */

            // What type of file?
            try {
                $requiredFile = null;
                switch ($node->getAttribute('type')) {

                    case 'media':
                        $requiredFile = $this->requiredFileFactory->getByDisplayAndMedia($this->display->displayId, $node->getAttribute('id'));
                        break;

                    case 'layout':
                        $requiredFile = $this->requiredFileFactory->getByDisplayAndLayout($this->display->displayId, $node->getAttribute('id'));
                        break;

                    case 'resource':
                        $requiredFile = $this->requiredFileFactory->getByDisplayAndWidget($this->display->displayId, $node->getAttribute('id'));
                        break;

                    default:
                        $this->getLog()->debug('Skipping unknown node in media inventory: %s - %s.', $node->getAttribute('type'), $node->getAttribute('id'));
                        continue;
                }

                // File complete?
                $complete = $node->getAttribute('complete');
                $requiredFile->complete = $complete;
                $requiredFile->save();

                // If this item is a 0 then set not complete
                if ($complete == 0)
                    $mediaInventoryComplete = 2;
            }
            catch (NotFoundException $e) {
                $this->getLog()->error('Unable to find file in media inventory: ' . $node->getAttribute('type') . '. ' . $node->getAttribute('id'));
            }
        }

        $this->display->mediaInventoryStatus = $mediaInventoryComplete;

        // Only call save if this property has actually changed.
        if ($this->display->hasPropertyChanged('mediaInventoryStatus')) {
            // If we are complete, then drop the player nonce cache
            if ($this->display->mediaInventoryStatus == 1) {
                $this->pool->deleteItem('/display/nonce/' . $this->display->displayId);
            }

            $this->display->saveMediaInventoryStatus();
        }

        $this->logBandwidth($this->display->displayId, Bandwidth::$MEDIAINVENTORY, strlen($inventory));

        return true;
    }
    /**
     * @param $serverKey
     * @param $hardwareKey
     * @param $layoutId
     * @param $regionId
     * @param $mediaId
     * @return mixed
     * @throws \Xibo\Exception\XMDSFault
     */
    protected function doGetResourceWithPreferredDisplayDim($serverKey, $hardwareKey, $layoutId, $regionId, $preferredDisplayWidth, $preferredDisplayHeight, $mediaId)
    {
        //$this->logProcessor->setRoute('doGetResourceWithPreferredDisplayDim');

        // Sanitize
        $serverKey = $this->getSanitizer()->string($serverKey);
        $hardwareKey = $this->getSanitizer()->string($hardwareKey);
        $layoutId = $this->getSanitizer()->int($layoutId);
        $preferredDisplayWidth = $this->getSanitizer()->int(floor($preferredDisplayWidth));
        $preferredDisplayHeight = $this->getSanitizer()->int(floor($preferredDisplayHeight));
        $mediaId = $this->getSanitizer()->int($mediaId);

        // Check the serverKey matches
        if ($serverKey != $this->getConfig()->GetSetting('SERVER_KEY'))
            throw new \Xibo\Exception\XMDSFault('Sender', 'The Server key you entered does not match with the server key at this address');

        // Make sure we are sticking to our bandwidth limit
        if (!$this->checkBandwidth())
            throw new \Xibo\Exception\XMDSFault('Receiver', "Bandwidth Limit exceeded");

        // Auth this request...
        if (!$this->authDisplay($hardwareKey))
            throw new \Xibo\Exception\XMDSFault('Receiver', "This display client is not licensed");

        // The MediaId is actually the widgetId
        try {
            //$requiredFile = $this->requiredFileFactory->getByDisplayAndResource($this->display->displayId, $layoutId, $regionId, $mediaId);
            //$thisregion = $this->regionFactory->getById($regionId);
            //$module = $this->moduleFactory->createWithWidgetAndPreferredDim($this->widgetFactory->loadByWidgetId($mediaId), $preferredDisplayWidth, $preferredDisplayHeight);
            $requiredFile = $this->requiredFileFactory->getByDisplayAndWidget($this->display->displayId, $mediaId);
            $module = $this->moduleFactory->createWithWidgetAndPreferredDim($this->widgetFactory->loadByWidgetId($mediaId), $preferredDisplayWidth, $preferredDisplayHeight);

            //$module = $this->moduleFactory->createWithWidget($this->widgetFactory->loadByWidgetId($mediaId), $this->regionFactory->getById($regionId));
            $resource = $module->getResource($this->display->displayId);

            $requiredFile->bytesRequested = $requiredFile->bytesRequested + strlen($resource);
            $requiredFile->save();

            if ($resource == '')
                throw new ControllerNotImplemented();
        }
        catch (NotFoundException $notEx) {
            throw new \Xibo\Exception\XMDSFault('Receiver', 'Requested an invalid file.');
        }
        catch (\Exception $e) {
            $this->getLog()->error('Unknown error during getResource. E = ' . $e->getMessage());
            $this->getLog()->debug($e->getTraceAsString());
            throw new \Xibo\Exception\XMDSFault('Receiver', 'Unable to get the media resource');
        }

        // Log Bandwidth
        $this->logBandwidth($this->display->displayId, Bandwidth::$GETRESOURCE, strlen($resource));

        return $resource;
    }
    /**
     * @param $serverKey
     * @param $hardwareKey
     * @param $layoutId
     * @param $regionId
     * @param $mediaId
     * @return mixed
     * @throws \Xibo\Exception\XMDSFault
     */
    protected function doGetResource($serverKey, $hardwareKey, $layoutId, $regionId, $mediaId)
    {
        // The MediaId is actually the widgetId
        try {
            $thisregion = $this->regionFactory->getById($regionId);
            $regionwidth = $thisregion == null ? 0 : floor($thisregion->width);
            $regionheight = $thisregion == null ? 0 : floor($thisregion->height);
            return $this->doGetResourceWithPreferredDisplayDim($serverKey, $hardwareKey, $layoutId, $regionId,
               $regionwidth,
                $regionheight,
                $mediaId);
        }
        catch (NotFoundException $notEx) {
            throw new \Xibo\Exception\XMDSFault('Receiver', 'Requested an invalid file.');
        }
        catch (\Exception $e) {
            $this->getLog()->error('Unknown error during getResource. E = ' . $e->getMessage());
            $this->getLog()->debug($e->getTraceAsString());
            throw new \Xibo\Exception\XMDSFault('Receiver', 'Unable to get the media resource');
        }
    }

    /**
     * PHONE_HOME if required
     */
    protected function phoneHome()
    {
        if ($this->getConfig()->GetSetting('PHONE_HOME') == 'On') {
            // Find out when we last PHONED_HOME :D
            // If it's been > 28 days since last PHONE_HOME then
            if ($this->getConfig()->GetSetting('PHONE_HOME_DATE') < (time() - (60 * 60 * 24 * 28))) {

                try {
                    $dbh = $this->getStore()->getConnection();

                    // Retrieve number of displays
                    $sth = $dbh->prepare('SELECT COUNT(*) AS Cnt FROM `display` WHERE `licensed` = 1');
                    $sth->execute();

                    $PHONE_HOME_CLIENTS = $sth->fetchColumn();

                    // Retrieve version number
                    $PHONE_HOME_VERSION = $this->getConfig()->Version('app_ver');

                    $PHONE_HOME_URL = $this->getConfig()->GetSetting('PHONE_HOME_URL') . "?id=" . urlencode($this->getConfig()->GetSetting('PHONE_HOME_KEY')) . "&version=" . urlencode($PHONE_HOME_VERSION) . "&numClients=" . urlencode($PHONE_HOME_CLIENTS);

                    if ($this->display->isAuditing())
                        $this->getLog()->notice("audit", "PHONE_HOME_URL " . $PHONE_HOME_URL, "xmds", "RequiredFiles");

                    // Set PHONE_HOME_TIME to NOW.
                    $sth = $dbh->prepare('UPDATE `setting` SET `value` = :time WHERE `setting`.`setting` = :setting LIMIT 1');
                    $sth->execute(array(
                        'time' => time(),
                        'setting' => 'PHONE_HOME_DATE'
                    ));

                    @file_get_contents($PHONE_HOME_URL);

                    if ($this->display->isAuditing())
                        $this->getLog()->notice("audit", "PHONE_HOME [OUT]", "xmds", "RequiredFiles");

                } catch (\Exception $e) {

                    $this->getLog()->error($e->getMessage());

                    return false;
                }
            }
        }
    }

    /**
     * Authenticates the display
     * @param string $hardwareKey
     * @return bool
     */
    protected function authDisplay($hardwareKey)
    {
        try {
            $this->display = $this->displayFactory->getByLicence($hardwareKey);

            if ($this->display->licensed != 1)
                return false;

            // Configure our log processor
            //$this->logProcessor->setDisplay($this->display->displayId, ($this->display->isAuditing()));

            return true;

        } catch (NotFoundException $e) {
            $this->getLog()->error($e->getMessage());
            return false;
        }
    }

    /**
     * Alert Display Up
     * @throws \phpmailerException
     */
    protected function alertDisplayUp()
    {
        $maintenanceEnabled = $this->getConfig()->GetSetting('MAINTENANCE_ENABLED');

        if ($this->display->loggedIn == 0) {

            $this->getLog()->info('Display %s was down, now its up.', $this->display->display);

            // Log display up
            $this->displayEventFactory->createEmpty()->displayUp($this->display->displayId);

            // Do we need to email?
            if ($this->display->emailAlert == 1 && ($maintenanceEnabled == 'On' || $maintenanceEnabled == 'Protected')
                && $this->getConfig()->GetSetting('MAINTENANCE_EMAIL_ALERTS') == 'On'
            ) {

                $subject = sprintf(__("Recovery for Display %s"), $this->display->display);
                $body = sprintf(__("Display %s with ID %d is now back online."), $this->display->display, $this->display->displayId);

                $notification = $this->notificationFactory->createEmpty();
                $notification->subject = $subject;
                $notification->body = $body;
                $notification->createdDt = $this->getDate()->getLocalDate(null, 'U');
                $notification->releaseDt = $this->getDate()->getLocalDate(null, 'U');
                $notification->isEmail = 1;
                $notification->isInterrupt = 0;
                $notification->userId = 0;
                $notification->isSystem = 1;

                // Add the system notifications group - if there is one.
                foreach ($this->userGroupFactory->getSystemNotificationGroups() as $group) {
                    /* @var UserGroup $group */
                    $notification->assignUserGroup($group);
                }

                // Get a list of people that have view access to the display?
                if ($this->getConfig()->GetSetting('MAINTENANCE_ALERTS_FOR_VIEW_USERS') == 1) {

                    foreach ($this->userGroupFactory->getByDisplayGroupId($this->display->displayGroupId) as $group) {
                        /* @var UserGroup $group */
                        $notification->assignUserGroup($group);
                    }
                }

                try {
                    $notification->save();
                } catch (\Exception $e) {
                    $this->getLog()->error('Unable to send email alert for display %s with subject %s and body %s', $this->display->display, $subject, $body);
                }
            } else {
                $this->getLog()->debug('No email required. Email Alert: %d, Enabled: %s, Email Enabled: %s.', $this->display->emailAlert, $maintenanceEnabled, $this->getConfig()->GetSetting('MAINTENANCE_EMAIL_ALERTS'));
            }
        }
    }

    /**
     * Get the Client IP Address
     * @return string
     */
    protected function getIp()
    {
        $clientIp = '';

        $keys = array('X_FORWARDED_FOR', 'HTTP_X_FORWARDED_FOR', 'CLIENT_IP', 'REMOTE_ADDR');
        foreach ($keys as $key) {
            if (isset($_SERVER[$key])) {
                $clientIp = $_SERVER[$key];
                break;
            }
        }

        return $clientIp;
    }

    /**
     * Check we haven't exceeded the bandwidth limits
     */
    protected function checkBandwidth()
    {
        $xmdsLimit = $this->getConfig()->GetSetting('MONTHLY_XMDS_TRANSFER_LIMIT_KB');

        if ($xmdsLimit <= 0)
            return true;

        try {
            $dbh = $this->getStore()->getConnection();

            // Test bandwidth for the current month
            $sth = $dbh->prepare('SELECT IFNULL(SUM(Size), 0) AS BandwidthUsage FROM `bandwidth` WHERE Month = :month');
            $sth->execute(array(
                'month' => strtotime(date('m') . '/02/' . date('Y') . ' 00:00:00')
            ));

            $bandwidthUsage = $sth->fetchColumn(0);

            return ($bandwidthUsage >= ($xmdsLimit * 1024)) ? false : true;

        } catch (\Exception $e) {
            $this->getLog()->error($e->getMessage());
            return false;
        }
    }

    /**
     * Log Bandwidth Usage
     * @param <type> $displayId
     * @param <type> $type
     * @param <type> $sizeInBytes
     */
    protected function logBandwidth($displayId, $type, $sizeInBytes)
    {
        $this->bandwidthFactory->createAndSave($type, $displayId, $sizeInBytes);
    }

    /**
     * Generate a file download path for HTTP downloads, taking into account the precence of a CDN.
     * @param $type
     * @param $itemId
     * @param $nonce
     * @return string
     */
    protected function generateRequiredFileDownloadPath($type, $itemId, $nonce)
    {
        $saveAsPath = Wsdl::getRoot() . '?file=' . $nonce . '&displayId=' . $this->display->displayId . '&type=' . $type . '&itemId=' . $itemId;
        // CDN?
        $cdnUrl = $this->configService->GetSetting('CDN_URL');
        if ($cdnUrl != '') {
            // Serve a link to the CDN
            return 'http' . (
                (
                    (isset($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) == 'on') ||
                    (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) == 'https')
                ) ? 's' : '')
                . '://' . $cdnUrl . urlencode($saveAsPath);
        } else {
            // Serve a HTTP link to XMDS
            return $saveAsPath;
        }
    }
    public function RegisterDisplay()
    {
        // Sanitize
        try {
            $serverKey = $this->getSanitizer()->getString('serverKey');
            $hardwareKey = $this->getSanitizer()->getString('hardwareKey');
            $displayName = $this->getSanitizer()->getString('displayName');
            $clientType = $this->getSanitizer()->getString('clientType');
            $clientVersion = $this->getSanitizer()->getString('clientVersion');
            $clientCode = $this->getSanitizer()->getInt('clientCode');
            $macAddress = $this->getSanitizer()->getString('macAddress');
            $clientAddress = $this->getIp();
            $xmrChannel = $this->getSanitizer()->getString('xmrChannel');
            $xmrPubKey = trim($this->getSanitizer()->getString('xmrPubKey'));
            $operatingSystem = $this->getSanitizer()->getString('operatingSystem');
            $retData = $this->doRegisterDisplay($serverKey, $hardwareKey, $displayName, $clientType, $clientVersion, $clientCode, $operatingSystem, $macAddress, $xmrChannel, $xmrPubKey);

            // Return
            $this->getState()->hydrate([
                'success' => 1,
                'httpState' => 201,
                'message' => sprintf(__('registered display: %s'), $this->display->getId()),
                'id' => $this->display->displayId,
                'data' => $retData
            ]);        
        } catch(\Exception $e)
        {
            // Return
            $this->getState()->hydrate([
                'success' => 0,
                'httpState' => 201,
                'message' => sprintf(__('failed to registered display: %s'), "")
            ]); 
        }
        $this->returnAsJson();
    }
    /* start of all public protocol */
  /**
     * Registers a new display
     * @param string $serverKey
     * @param string $hardwareKey
     * @param string $displayName
     * @param string $clientType
     * @param string $clientVersion
     * @param int $clientCode
     * @param string $operatingSystem
     * @param string $macAddress
     * @param string $xmrChannel
     * @param string $xmrPubKey
     * @return string
     * @throws \Xibo\Exception\XMDSFault
     */
    public function doRegisterDisplay($serverKey, $hardwareKey, $displayName, $clientType, $clientVersion, $clientCode, $operatingSystem, $macAddress, $xmrChannel, $xmrPubKey)
    {
        ////$this->logProcessor->setRoute('RegisterDisplay');

        // Sanitize
        $serverKey = $this->getSanitizer()->string($serverKey);
        $hardwareKey = $this->getSanitizer()->string($hardwareKey);
        $displayName = $this->getSanitizer()->string($displayName);
        $clientType = $this->getSanitizer()->string($clientType);
        $clientVersion = $this->getSanitizer()->string($clientVersion);
        $clientCode = $this->getSanitizer()->int($clientCode);
        $macAddress = $this->getSanitizer()->string($macAddress);
        $clientAddress = $this->getIp();
        $xmrChannel = $this->getSanitizer()->string($xmrChannel);
        $xmrPubKey = trim($this->getSanitizer()->string($xmrPubKey));

        if ($xmrPubKey != '' && !str_contains($xmrPubKey, 'BEGIN PUBLIC KEY')) {
            $xmrPubKey = "-----BEGIN PUBLIC KEY-----\n" . $xmrPubKey . "\n-----END PUBLIC KEY-----\n";
        }

        // Check the serverKey matches
        if ($serverKey != $this->getConfig()->GetSetting('SERVER_KEY'))
            throw new \Xibo\Exception\XMDSFault('Sender', 'The Server key you entered does not match with the server key at this address');

        // Check the Length of the hardwareKey
        if (strlen($hardwareKey) > 40)
            throw new \Xibo\Exception\XMDSFault('Sender', 'The Hardware Key you sent was too long. Only 40 characters are allowed (SHA1).');

        // Return an XML formatted string
        $return = new \DOMDocument('1.0');
        $displayElement = $return->createElement('display');
        $return->appendChild($displayElement);

        // Check in the database for this hardwareKey
        try {
            $display = $this->displayFactory->getByLicence($hardwareKey);
            $this->display = $display;

            ////$this->logProcessor->setDisplay($display->displayId, ($display->isAuditing()));

            // Audit in
            $this->getLog()->debug('serverKey: ' . $serverKey . ', hardwareKey: ' . $hardwareKey . ', displayName: ' . $displayName . ', macAddress: ' . $macAddress);

            // Now
            $dateNow = $this->getDate()->parse();

            // Append the time
            $displayElement->setAttribute('date', $this->getDate()->getLocalDate($dateNow));
            $displayElement->setAttribute('timezone', $this->getConfig()->GetSetting('defaultTimezone'));

            // Determine if we are licensed or not
            if ($display->licensed == 0) {
                // It is not licensed
                $displayElement->setAttribute('status', 2);
                $displayElement->setAttribute('code', 'WAITING');
                $displayElement->setAttribute('message', 'Display is awaiting licensing approval from an Administrator.');

            } else {
                // It is licensed
                $displayElement->setAttribute('status', 0);
                $displayElement->setAttribute('code', 'READY');
                $displayElement->setAttribute('message', 'Display is active and ready to start.');
                $displayElement->setAttribute('version_instructions', $display->versionInstructions);

                // Display Settings
                $settings = $display->getSettings();

                // Create the XML nodes
                foreach ($settings as $arrayItem) {

                    // Override the XMR address if empty
                    if (strtolower($arrayItem['name']) == 'xmrnetworkaddress' && $arrayItem['value'] == '') {
                        $arrayItem['value'] = $this->getConfig()->GetSetting('XMR_PUB_ADDRESS');
                    }

                    // Append Local Time to the root element
                    if (strtolower($arrayItem['name']) == 'displaytimezone' && $arrayItem['value'] != '') {
                        // Calculate local time
                        $dateNow->timezone($arrayItem['value']);

                        // Append Local Time
                        $displayElement->setAttribute('localDate', $this->getDate()->getLocalDate($dateNow));
                    }

                    $node = $return->createElement($arrayItem['name'], (isset($arrayItem['value']) ? $arrayItem['value'] : $arrayItem['default']));
                    $node->setAttribute('type', $arrayItem['type']);
                    $displayElement->appendChild($node);
                }

                // Add some special settings
                $nodeName = ($clientType == 'windows') ? 'DisplayName' : 'displayName';
                $node = $return->createElement($nodeName, $display->display);
                $node->setAttribute('type', 'string');
                $displayElement->appendChild($node);

                $nodeName = ($clientType == 'windows') ? 'ScreenShotRequested' : 'screenShotRequested';
                $node = $return->createElement($nodeName, $display->screenShotRequested);
                $node->setAttribute('type', 'checkbox');
                $displayElement->appendChild($node);

                // Commands
                $commands = $display->getCommands();

                if (count($commands) > 0) {
                    // Append a command element
                    $commandElement = $return->createElement('commands');
                    $displayElement->appendChild($commandElement);

                    // Append each individual command
                    foreach ($display->getCommands() as $command) {
                        /* @var \Xibo\Entity\Command $command */
                        // here, command code should not contain blank
                        $node = $return->createElement($command->code);
                        $commandString = $return->createElement('commandString', $command->commandString);
                        $validationString = $return->createElement('validationString', $command->validationString);

                        $node->appendChild($commandString);
                        $node->appendChild($validationString);

                        $commandElement->appendChild($node);
                    }
                }

                // Check to see if the channel/pubKey are already entered
                if ($display->isAuditing()) {
                    $this->getLog()->debug('xmrChannel: [' . $xmrChannel . ']. xmrPublicKey: [' . $xmrPubKey . ']');
                }

                // Update the Channel
                $display->xmrChannel = $xmrChannel;
                // Update the PUB Key only if it has been cleared
                if ($display->xmrPubKey == '' || $display->xmrPubKey == "\n")
                    $display->xmrPubKey = $xmrPubKey;

                // Send Notification if required
                $this->alertDisplayUp();
            }

        } catch (NotFoundException $e) {

            // Add a new display
            try {
                $display = $this->displayFactory->createEmpty();
                $this->display = $display;
                $display->display = $displayName;
                $display->auditingUntil = 0;
                $display->defaultLayoutId = 4;
                $display->license = $hardwareKey;
                $display->licensed = 0;
                $display->incSchedule = 0;
                $display->clientAddress = $this->getIp();
                $display->xmrChannel = $xmrChannel;
                $display->xmrPubKey = $xmrPubKey;
            }
            catch (\InvalidArgumentException $e) {
                throw new \Xibo\Exception\XMDSFault('Sender', $e->getMessage());
            }

            $displayElement->setAttribute('status', 1);
            $displayElement->setAttribute('code', 'ADDED');
            $displayElement->setAttribute('message', 'Display added and is awaiting licensing approval from an Administrator.');
        }


        $display->lastAccessed = time();
        $display->loggedIn = 1;
        $display->clientAddress = $clientAddress;
        $display->macAddress = $macAddress;
        $display->clientType = $clientType;
        $display->clientVersion = $clientVersion;
        $display->clientCode = $clientCode;
        //$display->operatingSystem = $operatingSystem;
        $display->save(Display::$saveOptionsMinimum);

        // Log Bandwidth
        $returnXml = $return->saveXML();
        $this->logBandwidth($display->displayId, Bandwidth::$REGISTER, strlen($returnXml));

        // Audit our return
        $this->getLog()->debug($returnXml, $display->displayId);

        return $returnXml;
    }
    /**
     * Gets additional resources for assigned media
     * @param string $serverKey
     * @param string $hardwareKey
     * @param int $layoutId
     * @param string $regionId
     * @param string $mediaId
     * @return mixed
     * @throws \Xibo\Exception\XMDSFault
     */
    function GetResource()
    {
        //return $this->doGetResource($serverKey, $hardwareKey, $layoutId, $regionId, $mediaId);

        try {
            $serverKey = $this->getSanitizer()->getString('serverKey');
            $hardwareKey = $this->getSanitizer()->getString('hardwareKey');
            $layoutId = $this->getSanitizer()->getInt('layoutId');
            $regionId = $this->getSanitizer()->getInt('regionId');
            $mediaId = $this->getSanitizer()->getInt('mediaId');

            $retData = $this->doGetResource($serverKey, $hardwareKey, $layoutId, $regionId, $mediaId);

            // Return
            $this->getState()->hydrate([
                'success' => 1,
                'httpState' => 201,
                'message' => sprintf(__('GetResource: %s'), $this->display->getId()),
                'id' => $this->display->displayId,
                'data' => $retData,
                'extra' => ['base64encoded' => 0]
            ]);        
        } catch (\Exception $e)
        {
            // Return
            $this->getState()->hydrate([
                'success' => 0,
                'httpState' => 201,
                'message' => sprintf(__('failed to GetResource: %s'), "")
            ]); 
        }
        $this->returnAsJson();
    }

    /**
     * Returns the schedule for the hardware key specified
     * @return string
     * @param string $serverKey
     * @param string $hardwareKey
     * @throws \Xibo\Exception\XMDSFault
     */
    function Schedule()
    {
        try {
            $serverKey = $this->getSanitizer()->getString('serverKey');
            $hardwareKey = $this->getSanitizer()->getString('hardwareKey');      
            $retData = $this->doSchedule($serverKey, $hardwareKey, ['dependentsAsNodes' => true, 'includeOverlays' => true]);

            // Return
            $this->getState()->hydrate([
                'success' => 1,
                'httpState' => 201,
                'message' => sprintf(__('Get Schedule for display: %s'), $this->display->getId()),
                'id' => $this->display->displayId,
                'data' => $retData
            ]);        
        } catch (\Exception $e)
        {
            // Return
            $this->getState()->hydrate([
                'success' => 0,
                'httpState' => 201,
                'message' => sprintf(__('failed to get Schedule files for display: %s'), "")
            ]); 
        }
        $this->returnAsJson();          
    }


    /**
     * Returns a string containing the required files xml for the requesting display
     * @param string $serverKey The Server Key
     * @param string $hardwareKey Display Hardware Key
     * @return string $requiredXml Xml Formatted String
     * @throws \Xibo\Exception\XMDSFault
     */
    function RequiredFiles()
    {
        try {
            $httpDownloads = ($this->getConfig()->GetSetting('SENDFILE_MODE') != 'Off');
            $serverKey = $this->getSanitizer()->getString('serverKey');
            $hardwareKey = $this->getSanitizer()->getString('hardwareKey');      
            $retData = $this->doRequiredFiles($serverKey, $hardwareKey, $httpDownloads);

            // Return
            $this->getState()->hydrate([
                'success' => 1,
                'httpState' => 201,
                'message' => sprintf(__('Required files for display: %s'), $this->display->getId()),
                'id' => $this->display->displayId,
                'data' => $retData
            ]);        
        } catch(\Exception $e)
        {
            // Return
            $this->getState()->hydrate([
                'success' => 0,
                'httpState' => 201,
                'message' => sprintf(__('failed to get Required files for display: %s'), "")
            ]); 
        }
        $this->returnAsJson();        
    }
    function GetFile()
    {
        try {
            $serverKey = $this->getSanitizer()->getString('serverKey');
            $hardwareKey = $this->getSanitizer()->getString('hardwareKey');      
            $fileId = $this->getSanitizer()->getInt('fileId');      
            $fileType = $this->getSanitizer()->getString('fileType');      
            $chunkOffset = $this->getSanitizer()->getInt('chunkOffset');      
            $chunkSize = $this->getSanitizer()->getInt('chunkSize');      
            
            $retData = $this->doGetFile($serverKey, $hardwareKey, $fileId, $fileType, $chunkOffset, $chunkSize);

            // Return
            $this->getState()->hydrate([
                'success' => 1,
                'httpState' => 201,
                'message' => sprintf(__('Get files for display: %s'), $this->display->getId()),
                'id' => $this->display->displayId,
                'data' => base64_encode($retData),
                'extra' => ['base64encoded' => 1]
            ]);        
        } catch (\Exception $e)
        {
            // Return
            $this->getState()->hydrate([
                'success' => 0,
                'httpState' => 201,
                'message' => sprintf(__('failed to get file files for display: %s'), "")
            ]); 
        };

        $this->returnAsJson();          
    }
/**
     * Get File
     * @param string $serverKey The ServerKey for this CMS
     * @param string $hardwareKey The HardwareKey for this Display
     * @param int $fileId The ID
     * @param string $fileType The File Type
     * @param int $chunkOffset The Offset of the Chunk Requested
     * @param string $chunkSize The Size of the Chunk Requested
     * @return mixed
     * @throws \Xibo\Exception\XMDSFault
     */
    function doGetFile($serverKey, $hardwareKey, $fileId, $fileType, $chunkOffset, $chunkSize)
    {
        ////$this->logProcessor->setRoute('GetFile');

        // Sanitize
        $serverKey = $this->getSanitizer()->string($serverKey);
        $hardwareKey = $this->getSanitizer()->string($hardwareKey);
        $fileId = $this->getSanitizer()->int($fileId);
        $fileType = $this->getSanitizer()->string($fileType);
        $chunkOffset = $this->getSanitizer()->int($chunkOffset);
        $chunkSize = $this->getSanitizer()->int($chunkSize);

        $libraryLocation = $this->getConfig()->GetSetting("LIBRARY_LOCATION");

        // Check the serverKey matches
        if ($serverKey != $this->getConfig()->GetSetting('SERVER_KEY'))
            throw new \Xibo\Exception\XMDSFault('Sender', 'The Server key you entered does not match with the server key at this address');

        // Make sure we are sticking to our bandwidth limit
        if (!$this->checkBandwidth())
            throw new \Xibo\Exception\XMDSFault('Receiver', "Bandwidth Limit exceeded");

        // Authenticate this request...
        if (!$this->authDisplay($hardwareKey))
            throw new \Xibo\Exception\XMDSFault('Receiver', "This display client is not licensed");

        if ($this->display->isAuditing())
            $this->getLog()->debug('hardwareKey: ' . $hardwareKey . ', fileId: ' . $fileId . ', fileType: ' . $fileType . ', chunkOffset: ' . $chunkOffset . ', chunkSize: ' . $chunkSize);

        try {
            if ($fileType == "layout") {
                $fileId = $this->getSanitizer()->int($fileId);

                // Validate the nonce
                $requiredFile = $this->requiredFileFactory->getByDisplayAndLayout($this->display->displayId, $fileId);

                // Load the layout
                $layout = $this->layoutFactory->getById($fileId);
                $path = $layout->xlfToDisk();

                $file = file_get_contents($path);
                $chunkSize = filesize($path);

                $requiredFile->bytesRequested = $requiredFile->bytesRequested + $chunkSize;
                $requiredFile->save();

            } else if ($fileType == "media") {
                // Validate the nonce
                $requiredFile = $this->requiredFileFactory->getByDisplayAndMedia($this->display->displayId, $fileId);

                $media = $this->mediaFactory->getById($fileId);
                $this->getLog()->debug(json_encode($media));

                if (!file_exists($libraryLocation . $media->storedAs))
                    throw new NotFoundException('Media exists but file missing from library. ' . $libraryLocation);

                // Return the Chunk size specified
                if (!$f = fopen($libraryLocation . $media->storedAs, 'r'))
                    throw new NotFoundException('Unable to get file pointer');

                fseek($f, $chunkOffset);

                $file = fread($f, $chunkSize);

                // Store file size for bandwidth log
                $chunkSize = strlen($file);

                if ($chunkSize === 0)
                    throw new NotFoundException('Empty file');

                $requiredFile->bytesRequested = $requiredFile->bytesRequested + $chunkSize;
                $requiredFile->save();

            } else {
                throw new NotFoundException('Unknown FileType Requested.');
            }
        }
        catch (NotFoundException $e) {
            $this->getLog()->error('Not found FileId: ' . $fileId . '. FileType: ' . $fileType . '. ' . $e->getMessage());
            throw new \Xibo\Exception\XMDSFault('Receiver', 'Requested an invalid file.');
        }

        // Log Bandwidth
        $this->logBandwidth($this->display->displayId, Bandwidth::$GETFILE, $chunkSize);

        return $file;
    }
    /**
     * Returns the schedule for the hardware key specified
     * @return string
     * @param string $serverKey
     * @param string $hardwareKey
     * @throws \Xibo\Exception\XMDSFault
     */
    function Schedule4($serverKey, $hardwareKey)
    {
        return $this->doSchedule($serverKey, $hardwareKey);
    }

    /**
     * Black List
     * @param string $serverKey
     * @param string $hardwareKey
     * @param string $mediaId
     * @param string $type
     * @param string $reason
     * @return bool
     * @throws \Xibo\Exception\XMDSFault
     */
    function BlackList()
    {
        try {
            $serverKey = $this->getSanitizer()->getString('serverKey');
            $hardwareKey = $this->getSanitizer()->getString('hardwareKey');
            $mediaId = $this->getSanitizer()->getString('mediaId');
            $type = $this->getSanitizer()->getString('type');
            $reason = $this->getSanitizer()->getString('reason');
            $retData = $this->doBlackList($serverKey, $hardwareKey, $mediaId, $type, $reason);

            // Return
            $this->getState()->hydrate([
                'success' => 1,
                'httpState' => 201,
                'message' => sprintf(__('Blacklist media for display: %s'), $this->display->getId()),
                'id' => $this->display->displayId,
                'data' => $retData
            ]);        
        } catch (\Exception $e)
        {
            // Return
            $this->getState()->hydrate([
                'success' => 0,
                'httpState' => 201,
                'message' => sprintf(__('failed to Blacklist media for display: %s'), "")
            ]); 
        }
        $this->returnAsJson();         
    }

    /**
     * Submit client logging
     * @return bool
     * @param string $serverKey
     * @param string $hardwareKey
     * @param string $logXml
     * @throws \Xibo\Exception\XMDSFault
     */
    function SubmitLog()
    {
        try {
            $serverKey = $this->getSanitizer()->getString('serverKey');
            $hardwareKey = $this->getSanitizer()->getString('hardwareKey');
            $logXml = $this->getSanitizer()->getRawString('logXml');

            $retData = $this->doSubmitLog($serverKey, $hardwareKey, $logXml);

            // Return
            $this->getState()->hydrate([
                'success' => 1,
                'httpState' => 201,
                'message' => sprintf(__('Submit log for display: %s'), $this->display->getId()),
                'id' => $this->display->displayId,
                'data' => $retData
            ]);        
        } catch (\Exception $e)
        {
            // Return
            $this->getState()->hydrate([
                'success' => 0,
                'httpState' => 201,
                'message' => sprintf(__('failed to submit log for display: %s'), "")
            ]); 
        }
        $this->returnAsJson();         
    }

    /**
     * Submit display statistics to the server
     * @return bool
     * @param string $serverKey
     * @param string $hardwareKey
     * @param string $statXml
     * @throws \Xibo\Exception\XMDSFault
     */
    function SubmitStats()
    {
        try {
            $serverKey = $this->getSanitizer()->getString('serverKey');
            $hardwareKey = $this->getSanitizer()->getString('hardwareKey');
            $statXml = $this->getSanitizer()->getRawString('statXml');

            $retData = $this->doSubmitStats($serverKey, $hardwareKey, $statXml);

            // Return
            $this->getState()->hydrate([
                'success' => 1,
                'httpState' => 201,
                'message' => sprintf(__('Submit stats for display: %s'), $this->display->getId()),
                'id' => $this->display->displayId,
                'data' => $retData
            ]);        
        } catch (\Exception $e)
        {
            // Return
            $this->getState()->hydrate([
                'success' => 0,
                'httpState' => 201,
                'message' => sprintf(__('failed to submit stats for display: %s'), "")
            ]); 
        }
        $this->returnAsJson();         
    }

    /**
     * Store the media inventory for a client
     * @param string $serverKey
     * @param string $hardwareKey
     * @param string $inventory
     * @return bool
     * @throws \Xibo\Exception\XMDSFault
     */
    public function MediaInventory()
    {
        try {
            $serverKey = $this->getSanitizer()->getString('serverKey');
            $hardwareKey = $this->getSanitizer()->getString('hardwareKey');
            $inventory = $this->getSanitizer()->getRawString('inventory');

            $retData = $this->doMediaInventory($serverKey, $hardwareKey, $inventory);

            // Return
            $this->getState()->hydrate([
                'success' => 1,
                'httpState' => 201,
                'message' => sprintf(__('media inventory for display: %s'), $this->display->getId()),
                'id' => $this->display->displayId,
                'data' => $retData
            ]);        
        } catch (\Exception $e)
        {
            // Return
            $this->getState()->hydrate([
                'success' => 0,
                'httpState' => 201,
                'message' => sprintf(__('failed to media inventory for display: %s'), "")
            ]); 
        }
        $this->returnAsJson();        
    }

    /**
     * Gets additional resources for assigned media
     * @param string $serverKey
     * @param string $hardwareKey
     * @param int $layoutId
     * @param string $regionId
     * @param string $mediaId
     * @return mixed
     * @throws \Xibo\Exception\XMDSFault
     */
    function GetResource5($serverKey, $hardwareKey, $layoutId, $regionId, $mediaId)
    {
        return $this->doGetResource($serverKey, $hardwareKey, $layoutId, $regionId, $mediaId);
    }

    public function NotifyStatus()
    {
        try {
            $serverKey = $this->getSanitizer()->getString('serverKey');
            $hardwareKey = $this->getSanitizer()->getString('hardwareKey');
            $status = $this->getSanitizer()->getRawString('status');

            $retData = $this->doNotifyStatus($serverKey, $hardwareKey, $status);

            // Return
            $this->getState()->hydrate([
                'success' => 1,
                'httpState' => 201,
                'message' => sprintf(__('Notify Status for display: %s'), $this->display->getId()),
                'id' => $this->display->displayId,
                'data' => $retData
            ]);        
        } catch (\Exception $e)
        {
            // Return
            $this->getState()->hydrate([
                'success' => 0,
                'httpState' => 201,
                'message' => sprintf(__('failed to notify status for display: %s'), "")
            ]); 
        }
        $this->returnAsJson();        
    }
    /**
     * Notify Status
     * @param string $serverKey
     * @param string $hardwareKey
     * @param string $status
     * @return bool
     * @throws \Xibo\Exception\XMDSFault
     */
    public function doNotifyStatus($serverKey, $hardwareKey, $status)
    {
        //$this->logProcessor->setRoute('NotifyStatus');

        // Sanitize
        $serverKey = $this->getSanitizer()->string($serverKey);
        $hardwareKey = $this->getSanitizer()->string($hardwareKey);

        // Check the serverKey matches
        if ($serverKey != $this->getConfig()->GetSetting('SERVER_KEY'))
            throw new \Xibo\Exception\XMDSFault('Sender', 'The Server key you entered does not match with the server key at this address');

        // Make sure we are sticking to our bandwidth limit
        if (!$this->checkBandwidth())
            throw new \Xibo\Exception\XMDSFault('Receiver', "Bandwidth Limit exceeded");

        // Auth this request...
        if (!$this->authDisplay($hardwareKey))
            throw new \Xibo\Exception\XMDSFault('Receiver', 'This display client is not licensed');

        // Important to keep this logging in place (status screen notification gets logged)
        if ($this->display->isAuditing())
            $this->getLog()->debug($status);

        $this->logBandwidth($this->display->displayId, Bandwidth::$NOTIFYSTATUS, strlen($status));

        $status = json_decode($status, true);

        $this->display->currentLayoutId = $this->getSanitizer()->getInt('currentLayoutId', $this->display->currentLayoutId, $status);
        $this->display->storageAvailableSpace = $this->getSanitizer()->getInt('availableSpace', $this->display->storageAvailableSpace, $status);
        $this->display->storageTotalSpace = $this->getSanitizer()->getInt('totalSpace', $this->display->storageTotalSpace, $status);
        $this->display->lastCommandSuccess = $this->getSanitizer()->getCheckbox('lastCommandSuccess', $this->display->lastCommandSuccess, $status);
        $this->display->deviceName = $this->getSanitizer()->getString('deviceName', $this->display->deviceName, $status);

        // Touch the display record
        $this->display->save(Display::$saveOptionsMinimum);

        return true;
    }

    public function SubmitScreenShot()
    {
        try {
            $serverKey = $this->getSanitizer()->getString('serverKey');
            $hardwareKey = $this->getSanitizer()->getString('hardwareKey');
            $screenShot = $this->getSanitizer()->getRawString('screenShot');

            $retData = $this->doSubmitScreenShot($serverKey, $hardwareKey, $screenShot);

            // Return
            $this->getState()->hydrate([
                'success' => 1,
                'httpState' => 201,
                'message' => sprintf(__('Submit screenshot for display: %s'), $this->display->getId()),
                'id' => $this->display->displayId,
                'data' => $retData
            ]);        
        } catch(\Exception $e)
        {
            // Return
            $this->getState()->hydrate([
                'success' => 0,
                'httpState' => 201,
                'message' => sprintf(__('failed to submit screenshot for display: %s'), "")
            ]); 
        }
        $this->returnAsJson();        
    }
    /**
     * Submit ScreenShot
     * @param string $serverKey
     * @param string $hardwareKey
     * @param string $screenShot
     * @return bool
     * @throws \Xibo\Exception\XMDSFault
     */
    public function doSubmitScreenShot($serverKey, $hardwareKey, $screenShot)
    {
        //$this->logProcessor->setRoute('SubmitScreenShot');

        // Sanitize
        $serverKey = $this->getSanitizer()->string($serverKey);
        $hardwareKey = $this->getSanitizer()->string($hardwareKey);

         // please be noted: @screenShot is base64 encoded
         $screenShot = base64_decode($screenShot);

        $screenShotFmt = "jpg";
        $screenShotMime = "image/jpeg";
        $screenShotImg = false;

        $converted = false;
        $needConversion = false;

        // Check the serverKey matches
        if ($serverKey != $this->getConfig()->GetSetting('SERVER_KEY'))
            throw new \Xibo\Exception\XMDSFault('Sender', 'The Server key you entered does not match with the server key at this address');

        // Make sure we are sticking to our bandwidth limit
        if (!$this->checkBandwidth())
            throw new \Xibo\Exception\XMDSFault('Receiver', "Bandwidth Limit exceeded");

        // Auth this request...
        if (!$this->authDisplay($hardwareKey))
            throw new \Xibo\Exception\XMDSFault('Receiver', 'This display client is not licensed');

        if ($this->display->isAuditing())
            $this->getLog()->debug('Received Screen shot');

        // Open this displays screen shot file and save this.
        $location = $this->getConfig()->GetSetting('LIBRARY_LOCATION') . 'screenshots/' . $this->display->displayId . '_screenshot.' . $screenShotFmt;
/*
        foreach(array('imagick', 'gd') as $imgDriver) {
            Img::configure(array('driver' => $imgDriver));
            try {
                $screenShotImg = Img::make($screenShot);
            } catch (\Exception $e) {
                if ($this->display->isAuditing())
                    $this->getLog()->debug($imgDriver . " - " . $e->getMessage());
            }
            if($screenShotImg !== false) {
                if ($this->display->isAuditing())
                    $this->getLog()->debug("Use " . $imgDriver);
                break;
            }
        }

        if ($screenShotImg !== false) {
            $imgMime = $screenShotImg->mime(); 

            if($imgMime != $screenShotMime) {
                $needConversion = true;
                try {
                    if ($this->display->isAuditing())
                        $this->getLog()->debug("converting: '" . $imgMime . "' to '" . $screenShotMime . "'");
                    $screenShot = (string) $screenShotImg->encode($screenShotFmt);
                    $converted = true;
                } catch (\Exception $e) {
                    if ($this->display->isAuditing())
                        $this->getLog()->debug($e->getMessage());
                }
            }
        }

        // return early with false, keep screenShotRequested intact, let the Player retry.
        if ($needConversion && !$converted) {
            $this->logBandwidth($this->display->displayId, Bandwidth::$SCREENSHOT, filesize($location));
            throw new \Xibo\Exception\XMDSFault('Receiver', __('Incorrect Screen shot Format'));
        }
*/
        $fp = fopen($location, 'wb');
        fwrite($fp, $screenShot);
        fclose($fp);

        // Touch the display record
        $this->display->screenShotRequested = 0;
        $this->display->save(Display::$saveOptionsMinimum);

        $this->logBandwidth($this->display->displayId, Bandwidth::$SCREENSHOT, filesize($location));

        return true;
    }
}
