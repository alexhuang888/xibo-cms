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

class AIImage extends \Xibo\Widget\Image
{
    public $codeSchemaVersion = 1;
    /**
     * Install or Update this module
     * @param ModuleFactory $moduleFactory
     */
    public function installOrUpdate($moduleFactory)
    {
        if ($this->module == null) 
        {
            // Install
            $module = $moduleFactory->createEmpty();
            $module->name = 'AIImage';
            $module->type = 'AIImage';
            $module->class = 'Xibo\Custom\AIImage';
            $module->description = 'AI Image';
            $module->imageUri = 'forms/library.gif';
            $module->enabled = 1;
            $module->previewEnabled = 1;
            $module->assignable = 1;
            $module->regionSpecific = 1;
            $module->renderAs = 'html';
            $module->schemaVersion = $this->codeSchemaVersion;
            $module->defaultDuration = 60;
            $module->settings = [];

            $this->setModule($module);
            $this->installModule();
        }
    }    
    /**
     * Post-processing
     *  this is run after the media item has been created and after it is saved.
     * @param Media $media
     */
    public function postProcessXXX($media)
    {
        // here, we would like to send this media file to google cloud vision to get smart Tag
        // first, make sure it is an image type (or it is already?)
        $filePath = $this->getConfig()->GetSetting('LIBRARY_LOCATION') . $media->storedAs;
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

                    $newtag->assignItem($media->getItemType(), $media->getId(), $data['tagsscore'][$idx]);

                    $newtag->save();
                    $idx++;
                }
                $media->isaitagsgenerated = true;
                $media->save();
            }
        }
    }  

    /**
     * Pre-process
     *  this is run before the media item is saved
     * @param Media $media
     * @param string $filePath: file path in temp folder.
     */
    public function preProcessXXX($media, $filePath) 
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

    }     

    /**
     * Pre-process
     *  this is run before the media item is saved
     * @param Media $media
     * @param string $filePath: file path in temp folder.
     */
    public function preProcess($media, $filePath) 
    {
        return $this->aitagshelper->mediasmarttagextractorProc($media, $filePath);
        //return $this->aitagshelper->processnewmedia($this->getUser()->getId(), $media->getItemType(),
          //                                          $media->getId(), $filePath);
    }     

    public function postProcess($media)
    {
        $filePath = $this->getConfig()->GetSetting('LIBRARY_LOCATION') . $media->storedAs;

        return $this->aitagshelper->processnewmedia($this->getUser()->getId(), $media, $filePath);        
    }    
}
