<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2006-2015 Daniel Garner
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
namespace Xibo\Custom;

use Intervention\Image\ImageManagerStatic as Img;
use Respect\Validation\Validator as v;
use Xibo\Exception\NotFoundException;

class AITagsHelper extends \Xibo\Factory\BaseFactory
{
    public $tagFactory;
    public $widgetFactory;
    public $playlistFactory;
    public $regionFactory;
    public $mediaFactory;
    public $moduleFactory;

    public $helpService;
    public $dateService;
    public $configService;
    public $permissionFactory;

    public function __construct($store, $log, $sanitizerService, $user, $userFactory, $permissionFactory, 
                                $help, $date, $config,
                                $tagFactory, $playlistFactory, $regionFactory, $widgetFactory,
                                $mediaFactory, $moduleFactory)
    {
        $this->setCommonDependencies($store, $log, $sanitizerService);
        $this->setAclDependencies($user, $userFactory);
        
        $this->helpService = $help;
        $this->dateService = $date;
        $this->configService = $config;
        $this->permissionFactory = $permissionFactory;

        $this->tagFactory = $tagFactory;
        $this->playlistFactory = $playlistFactory;
        $this->regionFactory = $regionFactory;
        $this->widgetFactory = $widgetFactory;
        $this->mediaFactory = $mediaFactory;
        $this->moduleFactory = $moduleFactory;
    }

    public function matchtagsscore($tags1, $tags2)
    {
        $ch = curl_init();
        //$cfile = curl_file_create($filePath, mime_content_type($filePath), 'mediafile');
        $data1 = array('ws1' => $tags1, 'ws2' => $tags2);
        $data = array('json' => $data1);
        curl_setopt($ch, CURLOPT_URL, 'http://localhost:35370/tagsmatch/n_similarity');
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
    public function processnewmedia($userId, $media, $filePath)
    {
        // in this function, we just insert a media, and check if this media
        // can be insert to any Playlist
        // first, check if this media has ai-tag generated, or generate it now.

        if ($media->getItemType() != \Xibo\Entity\Media::ItemType())
            return;

        //$media = $this->mediaFactory->getById($itemid);

        if ($media->isaitagsgenerated == false || $media->getId() == null)
        {
            // generate ai tags first
            $this->mediasmarttagextractorProc($media, $filePath);
            $media->save();
        }
        $mediatags = $this->tagFactory->loadByItemId($media->getItemType(), $media->getId());
        $module = $this->moduleFactory->getByExtension(strtolower(substr(strrchr($media->fileName, '.'), 1)));
        $module = $this->moduleFactory->create($module->type);
        // then, for all playlist, check if it is ok to insert this media
        // find all playlist based on the displaygroups (or just all playlist?)
        $filterby = array('isaitagmatchable' => 1);
        $allpl = $this->playlistFactory->query(null, $filterby);

        foreach ($allpl as $thispl)
        {
            // get tags of Playlist
            $pltags = $this->tagFactory->loadByItemId($thispl->getItemType(), $thispl->getId());

            // check if this media is there alrady?
            if ($thispl->hasMedias($media->getId()) == false)
            {
                // match $pltags and $mediatags
                // if score > 0.5, put media to $thispl

                $matchscore = $this->matchtagsscore($pltags, $mediatags);
                if ($matchscore >= 0.5)
                {
                    // add media back to pl
                    $thispl->setChildObjectDependencies($regionFactory);

                    // Create a Widget and add it to our region
                    $widget = $this->widgetFactory->create($userId, $playlist->playlistId, $module->getModuleType(), $media->duration);

                    // Assign the widget to the module
                    $module->setWidget($widget);

                    // Set default options (this sets options on the widget)
                    $module->setDefaultWidgetOptions();

                    // Assign media
                    $widget->assignMedia($media->getId());

                    // Assign the new widget to the playlist
                    $thispl->assignWidget($widget);

                    // Save the playlist
                    $thispl->save(); 

                    // if we need to notify client? 
                }              
            }
        }
    }
    public function profiletextextractor()
    {
        $url = 'http://localhost:35360/profileaitags/profiletextextractor';
        $data = json_encode(array('profiletext' => urlencode($_POST['profiletext']), 'withScore' => $_POST['withScore']));
        $ch = curl_init($url);
        //curl_setopt($ch, CURLOPT_URL, $url);

        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        //curl_setopt($ch, CURLOPT_POST, 1);
        //CURLOPT_SAFE_UPLOAD defaulted to true in 5.6.0
        //So next line is required as of php >= 5.6.0
        //curl_setopt($ch, CURLOPT_SAFE_UPLOAD, false);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(                                                                          
                        'Content-Type: application/json',                                                                                
                        'Content-Length: ' . strlen($data))                                                                       
                    ); 
        $result = array();
        try {
            $result = curl_exec($ch);
        } catch (Exception $e)
        {
            $result = "{'result': 100}";
        }
        return $result;       
    } 
    public function mediasmarttagextractorProc($media, $filePath) 
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
                    $newtag = $this->tagFactory->tagFromString($gtag);
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
   
}
