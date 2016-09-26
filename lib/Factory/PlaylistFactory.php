<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2015 Spring Signage Ltd
 *
 * This file (PlaylistFactory.php) is part of Xibo.
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


namespace Xibo\Factory;


use Xibo\Entity\Playlist;
use Xibo\Exception\NotFoundException;
use Xibo\Service\DateServiceInterface;
use Xibo\Service\LogServiceInterface;
use Xibo\Service\SanitizerServiceInterface;
use Xibo\Storage\StorageServiceInterface;

/**
 * Class PlaylistFactory
 * @package Xibo\Factory
 */
class PlaylistFactory extends BaseFactory
{
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
     * @var tagFactory
     */
    private $tagFactory;

    /**
     * @var container
     */
    private $container;    
    /**
     * Construct a factory
     * @param StorageServiceInterface $store
     * @param LogServiceInterface $log
     * @param SanitizerServiceInterface $sanitizerService
     * @param DateServiceInterface $dateunassignItem
     * @param PermissionFactory $permissionFactory
     * @param WidgetFactory $widgetFactory
     */
    public function __construct($store, $log, $sanitizerService, $date, $permissionFactory, $widgetFactory, $tagFactory, $container)
    {
        $this->setCommonDependencies($store, $log, $sanitizerService);

        $this->dateService = $date;
        $this->permissionFactory = $permissionFactory;
        $this->widgetFactory = $widgetFactory;
        $this->tagFactory = $tagFactory;
        $this->container = $container;
    }

    /**
     * @return Playlist
     */
    public function createEmpty()
    {
        return new Playlist($this->getStore(), $this->getLog(), $this->dateService, $this->permissionFactory, $this->widgetFactory, $this->tagFactory, $this->container->layoutFactory);
    }

    /**
     * Load Playlists by
     * @param $regionId
     * @return array[Playlist]
     * @throws NotFoundException
     */
    public function getByRegionId($regionId)
    {
        return $this->query(null, array('disableUserCheck' => 1, 'regionId' => $regionId));
    }

    /**
     * Get by Id
     * @param int $playlistId
     * @return Playlist
     * @throws NotFoundException
     */
    public function getById($playlistId)
    {
        $playlists = $this->query(null, array('disableUserCheck' => 1, 'playlistId' => $playlistId));

        if (count($playlists) <= 0)
            throw new NotFoundException(__('Cannot find playlist'));

        return $playlists[0];
    }

    /**
     * Create a Playlist
     * @param string $name
     * @param int $ownerId
     * @return Playlist
     */
    public function create($name, $ownerId)
    {
        $playlist = $this->createEmpty();
        $playlist->name = $name;
        $playlist->ownerId = $ownerId;

        return $playlist;
    }

    public function query($sortOrder = null, $filterBy = null)
    {
        $entries = array();

        $params = array();
        $select = 'SELECT `playlist`.*, 
            (
                SELECT COUNT(*)
                  FROM widget
                 WHERE widget.playlistid = `playlist`.playlistid
              ) AS numberWidgets ';

        if ($this->getSanitizer()->getInt('regionId', $filterBy) !== null) {
            $select .= ' , `lkregionplaylist`.displayOrder ';
        }

        $body = '  FROM `playlist` ';
       
        // to get playlistLayoutID and playlistCampaignID

        if ($this->getSanitizer()->getInt('regionId', $filterBy) !== null) 
        {
            $body .= "  INNER JOIN `lkregionplaylist` ";
            $body .= "   ON `lkregionplaylist`.playlistId = `playlist`.playlistId ";             
            $body .= '        AND `lkregionplaylist`.regionId = :regionId ';
            $params['regionId'] = $this->getSanitizer()->getInt('regionId', $filterBy);
        }

        $body .= ' WHERE 1 = 1 ';
        // filter by playlistid
        if ($this->getSanitizer()->getInt('playlistId', $filterBy) != 0) {
            $body .= ' AND `playlist`.playlistId = :playlistId ';
            $params['playlistId'] = $this->getSanitizer()->getInt('playlistId', $filterBy);
        }
        // filter by name
        if ($this->getSanitizer()->getString('name', $filterBy) != 0) {
            $body .= ' AND `playlist`.name = :name ';
            $params['name'] = $this->getSanitizer()->getString('name', $filterBy);
        } 
        //  filter by Tags
        if ($this->getSanitizer()->getString('tags', $filterBy) != '') {
            $body .= " AND `playlist`.playlistId IN (
                SELECT `lklinkedtags`.itemid
                  FROM `tag`
                    INNER JOIN `lklinkedtags`
                    ON `lklinkedtags`.tagid = `tag`.tagId
                ";
            $i = 0;
            foreach (explode(',', $this->getSanitizer()->getString('tags', $filterBy)) as $tag) {
                $i++;

                if ($i == 1)
                    $body .= " WHERE ( tag LIKE :tags$i ";
                else
                    $body .= " OR tag LIKE :tags$i ";

                $params['tags' . $i] =  '%' . $tag . '%';
            }
            if ($i > 0)
            {
                $body.= (" ) AND `lklinkedtags`.itemtype = " . \Xibo\Entity\Playlist::ItemType() . " ");
            }
            else
            {
                $body .= ("WHERE `lklinkedtags`.itemtype = " . \Xibo\Entity\Playlist::ItemType() . " ");
            }
            $body .= " ) ";
        }               
        if (DBVERSION >= 210 && array_key_exists('isaitagmatchable', $filterBy))
        {
            if ($this->getSanitizer()->getInt('isaitagmatchable', $filterBy) != null)
            {
                if ($this->getSanitizer()->getInt('isaitagmatchable',0, $filterBy) < 2) 
                {
                    $body .= ' AND `playlist`.isaitagmatchable = :isaitagmatchable ';
                    $params['isaitagmatchable'] = $this->getSanitizer()->getInt('isaitagmatchable', 0, $filterBy);
                }
            }
        }
        // filter by playlistid
        if ($this->getSanitizer()->getInt('isUserPlaylist', $filterBy) != 0) 
        {
            $body .= ' AND `playlist`.isUserPlaylist = :isUserPlaylist ';
            $params['isUserPlaylist'] = $this->getSanitizer()->getInt('isUserPlaylist', $filterBy);
        }   
        // filter by playlistid
        if ($this->getSanitizer()->getInt('HasAnyWidget', $filterBy) != 0) 
        {
            if ($this->getSanitizer()->getInt('HasAnyWidget', $filterBy) == 1)
            {
                $body .= ' AND numberWidgets > 0 ';
            }
        }              
        // Sorting?
        $order = '';
        if (is_array($sortOrder))
            $order .= 'ORDER BY ' . implode(',', $sortOrder);

        $limit = '';
        // Paging
        if ($filterBy !== null && $this->getSanitizer()->getInt('start', $filterBy) !== null && $this->getSanitizer()->getInt('length', $filterBy) !== null) {
            $limit = ' LIMIT ' . intval($this->getSanitizer()->getInt('start', $filterBy), 0) . ', ' . $this->getSanitizer()->getInt('length', 10, $filterBy);
        }

        $sql = $select . $body . $order . $limit;

        foreach ($this->getStore()->select($sql, $params) as $row) {
            $entries[] = $this->createEmpty()->hydrate($row);
        }

        // Paging
        if ($limit != '' && count($entries) > 0) {
            $results = $this->getStore()->select('SELECT COUNT(*) AS total ' . $body, $params);
            $this->_countLast = intval($results[0]['total']);
        }

        return $entries;
    }
}