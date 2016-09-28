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
            'help' => $this->getHelp()->link('AI-Aware information', 'Add')
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
            'help' => $this->getHelp()->link('AI-Aware information', 'EditTags')
        ]);
    }
    public function retrieveMediaTagForm($itemtype, $itemid)
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
        $this->getState()->template = 'aitags-smarttagsextractor-edit';
        $this->getState()->setData([
            'aitagscvs' => $tagstr,
            'itemtype' => $itemtype,
            'itemid' => $itemid,
            'help' => $this->getHelp()->link('AI-Aware information', 'EditTags')
        ]);
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
        $schedulemediaplaylistprocessorqueue = $this->getSanitizer()->getString('schedulemediaplaylistqueue', 'off');


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
        // for now, we just handle media type
        if ($itemtype == \Xibo\Entity\Media::ItemType())
        {
            // make sure we have it set, or the system will re-generate it.
            $item->isaitagsgenerated = true;
            $item->save();            
            if ($schedulemediaplaylistprocessorqueue == 'on')
            {
                $this->container->aitagshelper->addToMediaPlayListProcessorQueue(
                                            $this->getUser()->getId(),
                                            \Xibo\Entity\Media::ItemType(),
                                            $itemid,
                                            "");              
            }
        }
        // Return
        $this->getState()->hydrate([
            'message' => sprintf(__('Edited %s'), 'AI-Aware information'),
            'itemtype' => $itemtype,
            'itemid' => $itemid,
            'data' => $item
        ]);
    }
    public function profiletextextractor()
    {
        $profileText = $_POST['profiletext'];
        $withScore = $_POST['withScore'];
        $result = $this->container->aitagshelper->profiletextextractor($profileText, $withScore);
        
        $this->getState()->hydrate([
            'message' => sprintf(__('Extract profile: %s'), 'tags'),
            'data' => json_decode($result)
        ]);   
    }
    /**
     * mediasmarttagextractor
     *  this is run before the media item is saved
     * @param Media $media
     * @param string $filePath: file path in temp folder.
     */
    public function mediasmarttagextractor() 
    {
        $itemtype = $_POST['itemtype'];
        $itemid = $_POST['itemid'];

        $media = $this->mediaFactory->getById($itemid);

        $moduleWidget = $this->moduleFactory->createWithMedia($media);

        $moduleWidget->setChildObjectDependencies($this->layoutFactory, $this->container->widgetFactory,
                                                    $this->displayGroupFactory,
                                                    $this->container->aitagshelper);
        //$filePath = $this->getConfig()->GetSetting('LIBRARY_LOCATION') . $media->storedAs;

        //$data = $this->container->aitagshelper->mediasmarttagextractorToStringArray($filePath);
        $data = $moduleWidget->getMediaAttributes(MAID_SMARTTAGS, $media);

        $this->getState()->hydrate([
            'message' => 'media smart tag retrieved.',
            'data' => $data
        ]);         
    }               
}
