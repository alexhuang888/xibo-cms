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
            $module->regionSpecific = 0;
            $module->renderAs = null;
            $module->schemaVersion = $this->codeSchemaVersion;
            $module->defaultDuration = 60;
            $module->settings = [];

            $this->setModule($module);
            $this->installModule();
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
        // we'd like to push it to maintain stage
        //return $this->aitagshelper->mediasmarttagextractorProc($media, $filePath);
        return;
    }     

    public function postProcess($media)
    {
        // we'd like to push it to maintain stage
        if ($this->getConfig()->GetSetting('GLOBAL_AIAWARE_ENABLE', 0) == 1 &&
                    $this->getConfig()->GetSetting('GLOBAL_AIAWARE_PUT_UPLOADED_MEDIA_IN_MATCHQUEUE', 0) == 1)
        {
            $filePath = $this->getConfig()->GetSetting('LIBRARY_LOCATION') . $media->storedAs;

            $this->aitagshelper->addToMediaPlayListProcessorQueue($this->getUser()->getId(),
                                                                    \Xibo\Entity\Media::ItemType(),
                                                                    $media->getId(),
                                                                    $filePath);      
    }    
}
