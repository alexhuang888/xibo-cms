<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2015 Spring Signage Ltd
 *
 * This file (Tag.php) is part of Xibo.
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
use Xibo\Service\LogServiceInterface;
use Xibo\Storage\StorageServiceInterface;


/**
 * Class Tag
 * @package Xibo\Entity
 *
 * @SWG\Definition()
 */
class Tag implements \JsonSerializable
{
    use EntityTrait;

    /**
     * @SWG\Property(description="The Tag ID")
     * @var int
     */
    public $tagId;

    /**
     * @SWG\Property(description="The Tag Name")
     * @var string
     */
    public $tag;

    /**
    ** score of this Tag
    **/
    public $tag_score;

    /**
    * item type: the item type this tag belong to
    **/
    public $itemtype;

    /**
    ** item id: the id this tag's item
    */
    public $itemid;

    /**
    * all item tuples associated with this tag [string(itemtype, itemid) => tag_score]
    */
    public $itemidtuples = [];

    /**
     * @SWG\Property(description="An array of layoutIDs with this Tag")
     * @var int[]
     */
    public $layoutIds = [];

    /**
     * @SWG\Property(description="An array of mediaIds with this Tag")
     * @var int[]
     */
    public $mediaIds = [];

    private $originalLayoutIds = [];
    private $originalMediaIds = [];

    private $originalitemtuples = [];

    /**
     * Entity constructor.
     * @param StorageServiceInterface $store
     * @param LogServiceInterface $log
     */
    public function __construct($store, $log)
    {
        $this->setCommonDependencies($store, $log);
    }

    public function __clone()
    {
        $this->tagId = null;
    }
    /**
     * @return int
     */
    public function getId()
    {
        return $this->tagId;
    }

    /**
     * @return int
     */
    public function getOwnerId()
    {
        return $this->getUser()->userId;
    }
    public function gettagscore($itemtype, $itemid)
    {
        $key = $itemtype . "_" . $itemid;

        if (!array_key_exists($key, $this->itemidtuples))
            return 0;

        return $this->itemidtuples[$key];    
    }
    public function updateData($tagid, $tag, $itemtype, $itemid, $itemscore)
    {
        $this->tagId = $tagid;
        $this->tag = $tag;
        $this->itemtype = $itemtype;
        $this->itemid = $itemid;
        $this->score = $itemscore;

        assignItem($itemtype, $itemid, $itemscore);
    }
    /**
     * Assign item
     * @param int $itemtype, int $itemid, float $itemscore
     */
    public function assignItem($itemtype, $itemid, $itemscore)
    {
        $this->load();
        $newkey = $itemtype . "_" . $itemid;

        if (!array_key_exists($newkey, $this->itemidtuples))
            $this->itemidtuples[$newkey] = [$itemtype, $itemid, $itemscore];
    }

    /**
     * Unassign item
     * @param int $itemtype, int $itemid, float $itemscore
     */
    public function unassignItem($itemtype, $itemid)
    {
        $this->load();
        $newkey = $itemtype . "_" . $itemid;
        if (array_key_exists($newkey, $this->itemidtuples))
            unset($this->itemidtuples[$newkey]);
    }
    /**
     * Assign Layout
     * @param int $layoutId
     */
    public function assignLayout($layoutId)
    {
        assignItem(1, $layoutId, 1);
    }

    /**
     * Unassign Layout
     * @param int $layoutId
     */
    public function unassignLayout($layoutId)
    {
        unassignItem(1, $layoutId);
    }

    /**
     * Assign Media
     * @param int $mediaId
     */
    public function assignMedia($mediaId)
    {
        assignItem(2, $mediaId, 1);
    }

    /**
     * Unassign Media
     * @param int $mediaId
     */
    public function unassignMedia($mediaId)
    {
        unassignItem(2, $mediaId);
    }
    /**
     * Link all assigned item
     */
    private function linkitems()
    {
        $itemsToLink = array_diff_key($this->itemtuples, $this->originalitemtuples);

        $this->getLog()->debug('Linking %d item to Tag %s', count($itemsToLink), $this->tag);

        // Layouts that are in layoutIds but not in originalLayoutIds
        foreach ($itemsToLink as $item) 
        {
            $this->getStore()->update('INSERT INTO `lklinkedtags` (tagid, itemtype, itemid, score) VALUES (:tagId, :itemtype, :itemid, :score) ON DUPLICATE KEY UPDATE itemid = itemid and itemtype=itemtype', array(
                'tagId' => $this->tagId,
                'itemtype' => $item[0],
                'itemid' => $item[1],
                'score' => $item[2]
            ));
        }
    }

        /**
     * Unlink all assigned items
     */
    private function unlinkItems()
    {
        // Layouts that are in the originalLayoutIds but not in the current layoutIds
        $itemsToUnlink = array_diff_key($this->originalitemtuples, $this->itemtuples);

        $this->getLog()->debug('Unlinking %d items from Tag %s', count($itemsToUnlink), $this->tag);

        if (count($itemsToUnlink) <= 0)
            return;

        // Unlink any layouts that are NOT in the collection
        $params = ['tagId' => $this->tagId];

        $sql = 'DELETE FROM `lklinkedtags` WHERE tagId = :tagId AND layoutId IN (0';

        $i = 0;
        foreach ($itemsToUnlink as $item) 
        {
            $params = ['tagId' => $this->tagId, 'itemtype' => $item[0], 'itemid' => $item[1], 'score' => $item[2]];

            $sql = 'DELETE FROM `lklinkedtags` WHERE tagId = :tagId AND itemtype = :itemtype AND itemid = :itemid';

            $this->getStore()->update($sql, $params);
        }
    }
    /**
     * Load
     */
    public function load()
    {
        if ($this->tagId == null || $this->loaded)
            return;

        $this->itemidtuples = [];
        foreach ($this->getStore()->select('SELECT itemtype, itemid, score FROM `lklinkedtags` WHERE tagid = :tagid', ['tagid' => $this->tagId]) as $row) 
        {
            //$this->layoutIds[] = $row['layoutId'];
            $newkey = $row['itemtype'] . "_" . $row['itemid'];
            $this->itemidtuples[$newkey] = $row['score'];
        }

        // Set the originals
        $this->originalitemtuples = $this->itemidtuples;

        $this->loaded = true;
    }

    /**
     * Save
     */
    public function save()
    {
        // If the tag doesn't exist already - save it
        if ($this->tagId == null || $this->tagId == 0)
            $this->add();

        // Manage the links to layouts and media
        $this->linkitems();
        $this->removeAssignments();

        $this->getLog()->debug('Saving Tag: %s, %d', $this->tag, $this->tagId);
    }

    /**
     * Remove Assignments
     */
    public function removeAssignments()
    {
        $this->unlinkItems();
    }

    /**
     * Add a tag
     * @throws \PDOException
     */
    private function add()
    {
        $this->tagId = $this->getStore()->insert('INSERT INTO `tag` (tag) VALUES (:tag) ON DUPLICATE KEY UPDATE tag = tag', array('tag' => $this->tag));
    }

    /**
     * Link all assigned layouts
     */
    private function linkLayouts()
    {
        $layoutsToLink = array_diff($this->layoutIds, $this->originalLayoutIds);

        $this->getLog()->debug('Linking %d layouts to Tag %s', count($layoutsToLink), $this->tag);

        // Layouts that are in layoutIds but not in originalLayoutIds
        foreach ($layoutsToLink as $layoutId) {
            $this->getStore()->update('INSERT INTO `lktaglayout` (tagId, layoutId) VALUES (:tagId, :layoutId) ON DUPLICATE KEY UPDATE layoutId = layoutId', array(
                'tagId' => $this->tagId,
                'layoutId' => $layoutId
            ));
        }
    }

    /**
     * Unlink all assigned Layouts
     */
    private function unlinkLayouts()
    {
        // Layouts that are in the originalLayoutIds but not in the current layoutIds
        $layoutsToUnlink = array_diff($this->originalLayoutIds, $this->layoutIds);

        $this->getLog()->debug('Unlinking %d layouts from Tag %s', count($layoutsToUnlink), $this->tag);

        if (count($layoutsToUnlink) <= 0)
            return;

        // Unlink any layouts that are NOT in the collection
        $params = ['tagId' => $this->tagId];

        $sql = 'DELETE FROM `lktaglayout` WHERE tagId = :tagId AND layoutId IN (0';

        $i = 0;
        foreach ($layoutsToUnlink as $layoutId) {checkEditable
            $i++;
            $sql .= ',:layoutId' . $i;
            $params['layoutId' . $i] = $layoutId;
        }

        $sql .= ')';



        $this->getStore()->update($sql, $params);
    }

    /**
     * Link all assigned media
     */
    private function linkMedia()
    {
        $mediaToLink = array_diff($this->mediaIds, $this->originalMediaIds);

        $this->getLog()->debug('Linking %d media to Tag %s', count($mediaToLink), $this->tag);

        foreach ($mediaToLink as $mediaId) {
            $this->getStore()->update('INSERT INTO `lktagmedia` (tagId, mediaId) VALUES (:tagId, :mediaId) ON DUPLICATE KEY UPDATE mediaId = mediaId', array(
                'tagId' => $this->tagId,
                'mediaId' => $mediaId
            ));
        }
    }

    /**
     * Unlink all assigned media
     */
    private function unlinkMedia()
    {
        $mediaToUnlink = array_diff($this->originalMediaIds, $this->mediaIds);

        $this->getLog()->debug('Unlinking %d media from Tag %s', count($mediaToUnlink), $this->tag);

        // Unlink any layouts that are NOT in the collection
        if (count($mediaToUnlink) <= 0)
            return;

        $params = ['tagId' => $this->tagId];

        $sql = 'DELETE FROM `lktagmedia` WHERE tagId = :tagId AND mediaId IN (0';

        $i = 0;
        foreach ($mediaToUnlink as $mediaId) {
            $i++;
            $sql .= ',:mediaId' . $i;
            $params['mediaId' . $i] = $mediaId;
        }

        $sql .= ')';



        $this->getStore()->update($sql, $params);
    }
}