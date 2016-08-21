<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2009-13 Daniel Garner
 *
 * This file is part of Xibo.
 *
 * Xibo is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * any later version. 
 *
 * Xibo is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with Xibo.  If not, see <http://www.gnu.org/licenses/>.
 */
namespace Xibo\Controller;

use Xibo\Entity\Display;
use Xibo\Exception\AccessDeniedException;
use Xibo\Exception\ConfigurationException;
use Xibo\Exception\NotFoundException;
use Xibo\Factory\CommandFactory;
use Xibo\Factory\DisplayFactory;
use Xibo\Factory\DisplayGroupFactory;
use Xibo\Factory\LayoutFactory;
use Xibo\Factory\MediaFactory;
use Xibo\Factory\ModuleFactory;
use Xibo\Factory\ScheduleFactory;
use Xibo\Factory\TagFactory;
use Xibo\Service\ConfigServiceInterface;
use Xibo\Service\DateServiceInterface;
use Xibo\Service\LogServiceInterface;
use Xibo\Service\PlayerActionServiceInterface;
use Xibo\Service\SanitizerServiceInterface;
use Xibo\XMR\ChangeLayoutAction;
use Xibo\XMR\CollectNowAction;
use Xibo\XMR\CommandAction;
use Xibo\XMR\OverlayLayoutAction;
use Xibo\XMR\RevertToSchedule;
require_once PROJECT_ROOT . '/lib/Helper/ItemIDDef.php';
/**
 * Class AITags
 * @package Xibo\Controller
 * this class is to handle tags query from all item type, and update tags to those items.
 * also, it helps to handle AI profile analysis.
 */
class AITags extends Base
{
    /**
     * @var PlayerActionServiceInterface
     */
    private $playerAction;

    /**
     * @var DisplayGroupFactory
     */
    private $displayGroupFactory;

    /**
     * @var DisplayFactory
     */
    private $displayFactory;

    /**
     * @var ModuleFactory
     */
    private $moduleFactory;

    /**
     * @var MediaFactory
     */
    private $mediaFactory;

    /**
     * @var LayoutFactory
     */
    private $layoutFactory;

    /**
     * @var CommandFactory
     */
    private $commandFactory;

    /**
     * @var ScheduleFactory
     */
    private $scheduleFactory;

    private $tagFactory;

    private $container;
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
    public function __construct($log, $sanitizerService, $state, $user, $help, $date, $config, $playerAction, $displayFactory, $displayGroupFactory, $layoutFactory, $moduleFactory, $mediaFactory, $commandFactory, $scheduleFactory, 
                                $tagFactory, $container)
    {
        $this->setCommonDependencies($log, $sanitizerService, $state, $user, $help, $date, $config);

        $this->playerAction = $playerAction;
        $this->displayFactory = $displayFactory;
        $this->displayGroupFactory = $displayGroupFactory;
        $this->layoutFactory = $layoutFactory;
        $this->moduleFactory = $moduleFactory;
        $this->mediaFactory = $mediaFactory;
        $this->commandFactory = $commandFactory;
        $this->scheduleFactory = $scheduleFactory;
        $this->tagFactory = $tagFactory;
        $this->container = $container;
    }

    /**
     * Shows an add form for a display group
     */
    public function addForm()
    {
        $this->getState()->template = 'aitags-edit';
        $this->getState()->setData([
            'help' => $this->getHelp()->link('AITags', 'Add')
        ]);
    }

    /**
     * Shows an edit form for a display group
     * @param int $displayGroupId
     */
    public function editTagForm($itemtype, $itemid)
    {
        // get the item own this tags
        $item = $this->container->itemCreatorFactory->createByItemID($itemtype, $itemid);
        // check if users can edit this item
        if (!$this->getUser()->checkEditable($item))
            throw new AccessDeniedException();

        $tags = $this->tagFactory->loadByItemId($itemtype, $itemid);
            
        $a = array_map(function($obj) { return $obj->tag; }, 
                        $tags);

        $tagstr = implode(", ", $a);
        $this->getState()->template = 'aitags-edit';
        $this->getState()->setData([
            'aitagscvs' => $tagstr,
            'itemtype' => $itemtype,
            'itemid' => $itemid,
            'help' => $this->getHelp()->link('AITags', 'EditTags')
        ]);
    }
    public function retrieveMediaTagForm($itemtype, $itemid)
    {
    
    }
    // to edit tags from edit tag form (need to find all related items and update to their tags database link)
    public function editTags($itemtype, $itemid)
    {        
        // get the item own this tags
        $item = $this->container->itemCreatorFactory->createByItemID($itemtype, $itemid);
        // check if users can edit this item
        if (!$this->getUser()->checkEditable($item))
            throw new AccessDeniedException();

        $oritags = $this->tagFactory->loadByItemId($itemtype, $itemid);
        $oritagstrarray = array_map(function($obj) { return $obj->tag; }, 
                        $oritags);


        $newtagstr = $this->getSanitizer()->getString('itemtags');
        $newtagstrarray = explode(",", $newtagstr);
        $updateToChildItems = $this->getSanitizer()->getString('updateInChildItems', 'off');
/*        
        $this->getSanitizer()->getString('profiletags');
        $this->getSanitizer()->getString('itemtags');
*/      
        $childitems = array();

        if ($updateToChildItems=='on')
        {
            $childitems = $item->getchilditems($this->container);
        }

        // here, $newtagstr is the string array we need
        // 1. for every string, create a tag 
        // 2. set tag data by tag->UpdateData()
        // 3. for this tag object, assign this $item
        // 4. save this tag
        foreach ($newtagstrarray as $newtagstr)
        {
            try {
                $thistag = $this->tagFactory->getByTag($newtagstr);
                $thistag->assignItem($itemtype, $itemid, 1.0);
            } catch (NotFoundException $e)
            {
                $thistag = $this->tagFactory->createEmpty();
                $thistag->updateData(null, $newtagstr, $itemtype, $itemid, 1.0);
            }
            if ($updateToChildItems=='on')
            {
                foreach ($childitems as $childitem)
                {
                    $thistag->assignItem($childitem[0], $childitem[1], 1.0);
                }
            }
            $thistag->save();
        }

        // find those diff simplexml_load_string
        $diffstrarray = array_diff($oritagstrarray, $newtagstrarray);
        foreach ($diffstrarray as $deletestr)
        {
            try {
                $thistag = $this->tagFactory->getByTag($deletestr);
                $thistag->unassignItem($itemtype, $itemid);
                $thistag->save();
            } catch (NotFoundException $e)
            {
                
            }           
        }

        // Return
        $this->getState()->hydrate([
            'message' => sprintf(__('Edited %s'), 'AI tags'),
            'itemtype' => $itemtype,
            'itemid' => $itemid,
            'data' => $item
        ]);
    }
    public function profiletextextractor()
    {
        $result = $this->container->aitagshelper->profiletextextractor();
        

        $this->getState()->hydrate([
            'message' => sprintf(__('Edited %s'), 'tags'),
            'data' => json_decode($result)
        ]);   
    }

    public function matchtagsscore($tags1, $tags2)
    {
        $ch = curl_init();
        //$cfile = curl_file_create($filePath, mime_content_type($filePath), 'mediafile');
        $data1 = array('ws1' => $tags1, 'ws2' => $tags2);
        $data = array('json' => $data1);
        curl_setopt($ch, CURLOPT_URL, 'http://localhost:35360/word2vec/n_similarity');
        curl_setopt($ch, CURLOPT_POST, 1);
        //CURLOPT_SAFE_UPLOAD defaulted to true in 5.6.0
        //So next line is required as of php >= 5.6.0
        //curl_setopt($ch, CURLOPT_SAFE_UPLOAD, false);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        try {
            $result = curl_exec($ch);
        } catch (Exception $e)
        {
            $result = "{'result': [100, 0]}";
        }
        // HERE, we should have tags from google service, update them to this media
        $data = json_decode($result, true);
        if (array_key_exists('result', $data))
        {
            if ($data['result'][0] == 0)
            {
                return $data['result'][1];  // match score
            }
        }   
        return 0.0;     
    }

    public function processnewmedia($userId, $itemtype, $itemid, 
                                    $filePath,
                                    $tagFactory, $playlistFactory,
                                    $regionFactory, $widgetFactory, 
                                    $mediaFactory, $moduleFactory)
    {
        // in this function, we just insert a media, and check if this media
        // can be insert to any Playlist
        // first, check if this media has ai-tag generated, or generate it now.

        if ($itemtype != Media::ItemType())
            return;

        $media = $mediaFactory->getById($itemid);

        if ($media->isaitagsgenerated == false)
        {
            // generate ai tags first
            $this->mediasmarttagextractorProc($media, $filePath, $tagFactory);
        }
        $mediatags = $tagFactory->loadByItemId($itemtype, $itemid);
        $module = $moduleFactory->getByExtension(strtolower(substr(strrchr($media->fileName, '.'), 1)));
        $module = $moduleFactory->create($module->type);
        // then, for all playlist, check if it is ok to insert this media
        // find all playlist based on the displaygroups (or just all playlist?)
        $filterby = array('isaitagmatchable' => 1);
        $allpl = $playlistFactory->query(null, $filterby);

        foreach ($allpl as $thispl)
        {
            // get tags of Playlist
            $pltags = $tagFactory->loadByItemId($thispl->getItemType(), $thispl->getId());

            // check if this media is there alrady?
            if ($thispl->hasMedias($itemid) == false)
            {
                // match $pltags and $mediatags
                // if score > 0.5, put media to $thispl

                $matchscore = matchtagsscore($pltags, $mediatags);
                if ($matchscore >= 0.5)
                {
                    // add media back to pl
                    $thispl->setChildObjectDependencies($regionFactory);

                    // Create a Widget and add it to our region
                    $widget = $widgetFactory->create($userId, $playlist->playlistId, $module->getModuleType(), $media->duration);

                    // Assign the widget to the module
                    $module->setWidget($widget);

                    // Set default options (this sets options on the widget)
                    $module->setDefaultWidgetOptions();

                    // Assign media
                    $widget->assignMedia($itemid);

                    // Assign the new widget to the playlist
                    $thispl->assignWidget($widget);

                    // Save the playlist
                    $thispl->save(); 

                    // if we need to notify client? 
                }              
            }
        }
    }
    public function mediasmarttagextractorProc($media, $filePath, $tagFactory) 
    {
        // here, we would like to send this media file to google cloud vision to get smart Tag
        // first, make sure it is an image type (or it is already?)
        //$filePath = $this->getConfig()->GetSetting('LIBRARY_LOCATION') . $media->storedAs;
        $ch = curl_init();
        $cfile = curl_file_create($filePath,mime_content_type($filePath), 'mediafile');
        $data = array('cmdtype'=> '4:10 6:10', 'filename' => $media->fileName, 'mediafile' => $cfile);
        curl_setopt($ch, CURLOPT_URL, 'http://localhost:35360/profileaitags/mediasmarttagretriever');
        curl_setopt($ch, CURLOPT_POST, 1);
        //CURLOPT_SAFE_UPLOAD defaulted to true in 5.6.0
        //So next line is required as of php >= 5.6.0
        curl_setopt($ch, CURLOPT_SAFE_UPLOAD, false);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $result = array();
        try {
            $result = curl_exec($ch);
        } catch (Exception $e)
        {
            $result = "{'result': 100}";
        }
        // HERE, we should have tags from google service, update them to this media
        $data = json_decode($result, true);
        if (array_key_exists('result', $data))
        {
            if ($data['result'] == 0 && array_key_exists('tags', $data) && array_key_exists('tagsscore', $data))
            {
                $idx = 0;
                // we have correct $result
                foreach ($data['tags'] as $gtag)
                {
                    $newtag = $tagFactory->tagFromString($gtag);
                    $newtag->tag_score = $data['tagsscore'][$idx];
                    //$newtag->assignItem($media->getItemType(), $media->getId(), $data['tagsscore'][$idx]);

                    //$newtag->save();
                    $media->assignTag($newtag);
                    $idx++;
                }
                $media->isaitagsgenerated = true;
            }
        }

        return $data;        
    } 
    /**
     * mediasmarttagextractor
     *  this is run before the media item is saved
     * @param Media $media
     * @param string $filePath: file path in temp folder.
     */
    public function mediasmarttagextractor($media, $filePath, $tagFactory) 
    {
        $data = $this->mediasmarttagextractorProc($media, $filePath, $tagFactory);

        $this->getState()->hydrate([
            'message' => 'media smart tag retrieved.',
            'data' => $data
        ]);         
    }               
}
