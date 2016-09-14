<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2015 Spring Signage Ltd
 *
 * This file (Playlist.php) is part of Xibo.
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


namespace Xibo\Entity;

use Respect\Validation\Validator as v;
use Xibo\Exception\NotFoundException;
use Xibo\Factory\PermissionFactory;
use Xibo\Factory\RegionFactory;
use Xibo\Factory\WidgetFactory;
use Xibo\Service\DateServiceInterface;
use Xibo\Service\LogServiceInterface;
use Xibo\Storage\StorageServiceInterface;
require_once PROJECT_ROOT . '/lib/Helper/ItemIDDef.php';

/**
 * Class Playlist
 * @package Xibo\Entity
 *
 * @SWG\Definition()
 */
class Playlist implements \JsonSerializable
{
    use EntityTrait;
    public static function ItemType() {
        return \ITID_PLAYLIST;
    }
    public function getItemType() {
        return \ITID_PLAYLIST;
    }    
    /**
     * @SWG\Property(description="The ID of this Playlist")
     * @var int
     */
    public $playlistId;

    /**
     * @SWG\Property(description="The userId of the User that owns this Playlist")
     * @var int
     */
    public $ownerId;

    /**
     * @SWG\Property(description="The Name of the Playlist")
     * @var string
     */
    public $name;

    /**
     * @SWG\Property(description="An array of Tags")
     * @var Tag[]
     */
    public $tags = [];

    // Private
    private $unassignTags = [];
    /**
     * @SWG\Property(description="An array of Regions this Playlist is assigned to")
     * @var Region[]
     */
    public $regions = [];

    /**
     * @SWG\Property(description="An array of Widgets assigned to this Playlist")
     * @var Widget[]
     */
    public $widgets = [];

    /**
     * @SWG\Property(description="An array of permissions")
     * @var Permission[]
     */
    public $permissions = [];

    /**
     * @SWG\Property(description="The display order of the Playlist when assigned to a Region")
     * @var int
     */
    public $displayOrder;

    /**
     * @var DateServiceInterface
     */
    public $dateService;

    /**
     * @var PermissionFactory
     */
    private $permissionFactory;

    /**
     * @var WidgetFactory
     */
    private $widgetFactory;

    /**
     * @var RegionFactory
     */
    private $regionFactory;

    /**
     * @var tagFactory
     */
    private $tagFactory;

    // if this playlist is allowed to assign media by matching ai tags?
    public $isaitagmatchable;

    public $lastaitagsmatchedDT;

    public $description;
    /**
     * Entity constructor.
     * @param StorageServiceInterface $store
     * @param LogServiceInterface $log
     * @param DateServiceInterface $date
     * @param PermissionFactory $permissionFactory
     * @param WidgetFactory $widgetFactory
     */
    public function __construct($store, $log, $date, $permissionFactory, $widgetFactory, $tagFactory)
    {
        $this->setCommonDependencies($store, $log);

        $this->dateService = $date;
        $this->permissionFactory = $permissionFactory;
        $this->widgetFactory = $widgetFactory;
        $this->tagFactory = $tagFactory;

        $this->excludeProperty('regions');
    }

    /**
     * @param $regionFactory
     * @return $this
     */
    public function setChildObjectDependencies($regionFactory)
    {
        $this->regionFactory = $regionFactory;
        return $this;
    }

    public function __clone()
    {
        $this->hash = null;
        $this->playlistId = null;
        $this->regions = [];
        $this->permissions = [];

        $this->widgets = array_map(function ($object) { return clone $object; }, $this->widgets);
    }

    public function __toString()
    {
        return sprintf('Playlist %s. Widgets = %d. PlaylistId = %d', $this->name, count($this->widgets), $this->playlistId);
    }

    private function hash()
    {
        return md5($this->playlistId . $this->ownerId . $this->name);
    }

    /**
     * Get the Id
     * @return int
     */
    public function getId()
    {
        return $this->playlistId;
    }

    /**
     * Get the OwnerId
     * @return int
     */
    public function getOwnerId()
    {
        return $this->ownerId;
    }

    /**
     * Sets the Owner
     * @param int $ownerId
     */
    public function setOwner($ownerId)
    {
        $this->ownerId = $ownerId;

        foreach ($this->widgets as $widget) {
            /* @var Widget $widget */
            $widget->setOwner($ownerId);
        }
    }

    /**
     * Get Widget at Index
     * @param int $index
     * @return Widget
     * @throws NotFoundException
     */
    public function getWidgetAt($index)
    {
        if ($index <= count($this->widgets)) {
            $zeroBased = $index - 1;
            if (isset($this->widgets[$zeroBased])) {
                return $this->widgets[$zeroBased];
            }
        }

        throw new NotFoundException(sprintf(__('Widget not found at index %d'), $index));
    }

    /**
     * @param Widget $widget
     */
    public function assignWidget($widget)
    {
        $this->load();

        $widget->displayOrder = count($this->widgets) + 1;
        $this->widgets[] = $widget;
    }
    /**
     * @param array[Tag] $tags
     */
    public function replaceTags($tags = [])
    {
        if (!is_array($this->tags) || count($this->tags) <= 0)
            $this->tags = $this->tagFactory->loadByItemId($this->getItemType(), $this->playlistId);

        $this->unassignTags = array_udiff($this->tags, $tags, function($a, $b) {
            /* @var Tag $a */
            /* @var Tag $b */
            return $a->tagId - $b->tagId;
        });

        $this->getLog()->debug('Tags to be removed: %s', json_encode($this->unassignTags));

        // Replace the arrays
        $this->tags = $tags;

        $this->getLog()->debug('Tags remaining: %s', json_encode($this->tags));
    }
    /**
     * Unassign tag
     * @param Tag $tag
     * @return $this
     */
    public function unassignTag($tag)
    {
        $this->tags = array_udiff($this->tags, [$tag], function($a, $b) {
            /* @var Tag $a */
            /* @var Tag $b */
            return $a->tagId - $b->tagId;
        });

        return $this;
    }

    /**
     * Assign Tag
     * @param Tag $tag
     * @return $this
     */
    public function assignTag($tag)
    {
        $this->load();

        if (!in_array($tag, $this->tags))
            $this->tags[] = $tag;

        return $this;
    }

    /**
     * Does the playlist have the provided tag?
     * @param $searchTag
     * @return bool
     */
    public function hasTag($searchTag)
    {
        $this->load();

        foreach ($this->tags as $tag) {
            /* @var Tag $tag */
            if ($tag->tag == $searchTag)
                return true;
        }

        return false;
    }            
    /**
     * Load
     * @param array $loadOptions
     */
    public function load($loadOptions = [])
    {
        if ($this->playlistId == null || $this->loaded)
            return;

        // Options
        $options = array_merge([
            'playlistIncludeRegionAssignments' => true,
            'loadPermissions' => true,
            'loadTags' => true,
            'loadWidgets' => true
        ], $loadOptions);

        $this->getLog()->debug('Load Playlist with %s', json_encode($options));

        // Load permissions
        if ($options['loadPermissions'])
            $this->permissions = $this->permissionFactory->getByObjectId(get_class(), $this->playlistId);

        // Load the widgets
        if ($options['loadWidgets']) {
            foreach ($this->widgetFactory->getByPlaylistId($this->playlistId) as $widget) {
                /* @var Widget $widget */
                $widget->load();
                $this->widgets[] = $widget;
            }
        }

        if ($options['playlistIncludeRegionAssignments']) {
            // Load the region assignments
            foreach ($this->regionFactory->getByPlaylistId($this->playlistId) as $region) {
                /* @var Region $region */
                $this->regions[] = $region;
            }
        }
        // Load all tags
        if ($options['loadTags'])
            $this->tags = $this->tagFactory->loadByItemId($this->getItemType(), $this->playlistId);
        $this->hash = $this->hash();
        $this->loaded = true;
    }
    /**
     * Validate this playlist
     */
    public function validate()
    {
        if (!v::string()->notEmpty()->validate($this->name))
            throw new \InvalidArgumentException(__('Please enter a playlist name'));
        if ($this->description != null)
        {
            if (!v::string()->length(null, 254)->validate($this->description))
                throw new \InvalidArgumentException(__('Description can not be longer than 254 characters'));
        }
    }
    /**
     * Save
     */
    public function save()
    {
        if ($this->playlistId == null || $this->playlistId == 0)
            $this->add();
        else if ($this->hash != $this->hash())
            $this->update();

        $this->validate();
        // Sort the widgets by their display order
        usort($this->widgets, function($a, $b) {
            /**
             * @var Widget $a
             * @var Widget$b
             */
            return $a->displayOrder - $b->displayOrder;
        });

        // Assert the Playlist on all widgets and apply a display order
        // this keeps the widgets in numerical order on each playlist
        $i = 0;
        foreach ($this->widgets as $widget) {
            /* @var Widget $widget */
            $i++;

            // Assert the playlistId
            $widget->playlistId = $this->playlistId;
            // Assert the displayOrder
            $widget->displayOrder = $i;
            $widget->save();
        }
        // always save tags
        {
            $this->getLog()->debug('Saving tags on %s', $this);

            // Save the tags
            if (is_array($this->tags)) 
            {
                foreach ($this->tags as $tag) 
                {
                    /* @var Tag $tag */

                    $this->getLog()->debug('Assigning tag %s', $tag->tag);

                    $tag->assignItem($this->getItemType(), $this->playlistId, 1.0);
                    $tag->save();
                }
            }

            // Remove unwanted ones
            if (is_array($this->unassignTags)) 
            {
                foreach ($this->unassignTags as $tag) 
                {
                    /* @var Tag $tag */
                    $this->getLog()->debug('Unassigning tag %s', $tag->tag);

                    $tag->unassignItem($this->getItemType(), $this->playlistId);
                    $tag->save();
                }
            }
        }        
    }

    /**
     * Delete
     */
    public function delete()
    {
        // We must ensure everything is loaded before we delete
        if (!$this->loaded)
            $this->load();

        $this->getLog()->debug('Deleting ' . $this);

        // Delete Permissions
        foreach ($this->permissions as $permission) {
            /* @var Permission $permission */
            $permission->deleteAll();
        }

        // Delete widgets
        foreach ($this->widgets as $widget) {
            /* @var Widget $widget */

            // Assert the playlistId
            $widget->playlistId = $this->playlistId;
            $widget->delete();
        }

        // Unlink regions
        foreach ($this->regions as $region) {
            /* @var Region $region */
            $region->unassignPlaylist($this);
            $region->save();
        }
        // Unassign all Tags
        foreach ($this->tags as $tag) 
        {
            /* @var Tag $tag */
            $tag->unassignItem($this->getItemType(), $this->getId());
            $tag->save();
        }
        // Delete this playlist
        $this->getStore()->update('DELETE FROM `playlist` WHERE playlistId = :playlistId', array('playlistId' => $this->playlistId));
    }

    /**
     * Add
     */
    private function add()
    {
        $this->getLog()->debug('Adding Playlist ' . $this->name);
        $this->validate();
        $sql = 'INSERT INTO `playlist` (`name`, `ownerId`, `description`, `isaitagmatchable`) VALUES (:name, :ownerId, :description, :isaitagmatchable)';
        $this->playlistId = $this->getStore()->insert($sql, array(
            'name' => $this->name,
            'ownerId' => $this->ownerId,
            'description' => $this->description,
            'isaitagmatchable' => $this->isaitagmatchable
        ));
    }

    /**
     * Update
     */
    private function update()
    {
        $this->getLog()->debug('Updating Playlist ' . $this->name . '. Id = ' . $this->playlistId);
        $this->validate();

        $sql = 'UPDATE `playlist` SET `name` = :name, `description` = :description , `isaitagmatchable` = :isaitagmatchable WHERE `playlistId` = :playlistId';
        $this->getStore()->update($sql, array(
            'playlistId' => $this->playlistId,
            'name' => $this->name,
            'description' => $this->description,
            'isaitagmatchable' => $this->isaitagmatchable
        ));
    }

    /**
     * Notify all Layouts of a change to this playlist
     *  This only sets the Layout Status to require a build and to update the layout modified date
     *  once the build is triggered, either from the UI or maintenance it will assess the layout
     *  and call save() if required.
     *  Layout->save() will ultimately notify the interested display groups.
     */
    public function notifyLayouts()
    {
        $this->getStore()->update('
            UPDATE `layout` SET `status` = 3, `modifiedDT` = :modifiedDt WHERE layoutId IN (
              SELECT `region`.layoutId
                FROM `lkregionplaylist`
                  INNER JOIN `region`
                  ON region.regionId = `lkregionplaylist`.regionId
               WHERE `lkregionplaylist`.playlistId = :playlistId
            )
        ', [
            'playlistId' => $this->playlistId,
            'modifiedDt' => $this->dateService->getLocalDate()
        ]);
    }

    /**
     * Has layouts
     * @return bool
     */
    public function hasLayouts()
    {
        $results = $this->getStore()->select('SELECT COUNT(*) AS qty FROM `lkregionplaylist` WHERE playlistId = :playlistId', ['playlistId' => $this->playlistId]);

        return ($results[0]['qty'] > 0);
    }

    /**
     * Has media
     * @return bool
     */
    public function hasMedias($mediaId)
    {
        $results = $this->getStore()->select('SELECT COUNT(*) AS qty 
                                    FROM `lkwidgetmedia` 
                                    WHERE lkwidgetmedia.mediaId = :mediaid AND lkwidgetmedia.widgetId in (SELECT widget.widgetId from widget WHERE playlistId = :playlistId) ', 
                                    ['playlistId' => $this->playlistId, 'mediaid' => $mediaId]);

        return ($results[0]['qty'] > 0);
    }    
}