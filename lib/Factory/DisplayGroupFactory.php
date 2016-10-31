<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (DisplayGroup.php)
 */


namespace Xibo\Factory;


use Xibo\Entity\DisplayGroup;
use Xibo\Entity\User;
use Xibo\Exception\NotFoundException;
use Xibo\Service\LogServiceInterface;
use Xibo\Service\SanitizerServiceInterface;
use Xibo\Storage\StorageServiceInterface;

/**
 * Class DisplayGroupFactory
 * @package Xibo\Factory
 */
class DisplayGroupFactory extends BaseFactory
{
    /**
     * @var PermissionFactory
     */
    private $permissionFactory;

    /**
     * Construct a factory
     * @param StorageServiceInterface $store
     * @param LogServiceInterface $log
     * @param SanitizerServiceInterface $sanitizerService
     * @param User $user
     * @param UserFactory $userFactory
     * @param PermissionFactory $permissionFactory
     */
    public function __construct($store, $log, $sanitizerService, $user, $userFactory, $permissionFactory)
    {
        $this->setCommonDependencies($store, $log, $sanitizerService);
        $this->setAclDependencies($user, $userFactory);

        $this->permissionFactory = $permissionFactory;
    }

    /**
     * Create Empty
     * @return DisplayGroup
     */
    public function createEmpty()
    {
        return new DisplayGroup(
            $this->getStore(),
            $this->getLog(),
            $this,
            $this->permissionFactory
        );
    }

    /**
     * @param int $displayGroupId
     * @return DisplayGroup
     * @throws NotFoundException
     */
    public function getById($displayGroupId)
    {
        $groups = $this->query(null, ['disableUserCheck' => 1, 'displayGroupId' => $displayGroupId, 'isDisplaySpecific' => -1]);

        if (count($groups) <= 0)
            throw new NotFoundException();

        return $groups[0];
    }

    /**
     * @param int $displayId
     * @return array[DisplayGroup]
     */
    public function getByDisplayId($displayId)
    {
        return $this->query(null, ['disableUserCheck' => 1, 'displayId' => $displayId, 'isDisplaySpecific' => -1]);
    }

    /**
     * Get Display Groups by MediaId
     * @param int $mediaId
     * @return array[DisplayGroup]
     */
    public function getByMediaId($mediaId)
    {
        return $this->query(null, ['disableUserCheck' => 1, 'mediaId' => $mediaId, 'isDisplaySpecific' => -1]);
    }

    /**
     * Get Display Groups by eventId
     * @param int $eventId
     * @return array[DisplayGroup]
     */
    public function getByEventId($eventId)
    {
        return $this->query(null, ['disableUserCheck' => 1, 'eventId' => $eventId, 'isDisplaySpecific' => -1]);
    }

    /**
     * Get Display Groups by isDynamic
     * @param int $isDynamic
     * @return array[DisplayGroup]
     */
    public function getByIsDynamic($isDynamic)
    {
        return $this->query(null, ['disableUserCheck' => 1, 'isDynamic' => $isDynamic]);
    }

    /**
     * Get Display Groups by their ParentId
     * @param int $parentId
     * @return array[DisplayGroup]
     */
    public function getByParentId($parentId)
    {
        return $this->query(null, ['disableUserCheck' => 1, 'parentId' => $parentId]);
    }

    /**
     * Get Relationship Tree
     * @param $displayGroupId
     * @return DisplayGroup[]
     */
    public function getRelationShipTree($displayGroupId)
    {
        $tree = [];

        foreach ($this->getStore()->select('
            SELECT `displaygroup`.displayGroupId, `displaygroup`.displayGroup, depth, 1 AS level
              FROM `lkdgdg`
                INNER JOIN `displaygroup`
                ON `lkdgdg`.childId = `displaygroup`.displayGroupId
             WHERE `lkdgdg`.parentId = :displayGroupId
            UNION ALL
            SELECT `displaygroup`.displayGroupId, `displaygroup`.displayGroup, depth * -1, 0 AS level
              FROM `lkdgdg`
                INNER JOIN `displaygroup`
                ON `lkdgdg`.parentId = `displaygroup`.displayGroupId
             WHERE `lkdgdg`.childId = :displayGroupId AND `lkdgdg`.parentId <> :displayGroupId
            ORDER BY level, depth, displayGroup
        ', [
            'displayGroupId' => $displayGroupId
        ]) as $row) {
            $tree[] = $this->createEmpty()->hydrate($row);
        }

        return $tree;
    }

    /**
     * Get Display Groups assigned to Notifications
     * @param int $notificationId
     * @return array[DisplayGroup]
     */
    public function getByNotificationId($notificationId)
    {
        return $this->query(null, ['disableUserCheck' => 1, 'notificationId' => $notificationId, 'isDisplaySpecific' => -1]);
    }

    /**
     * @param array $sortOrder
     * @param array $filterBy
     * @return array[DisplayGroup]
     */
    public function query($sortOrder = null, $filterBy = null)
    {
        if ($sortOrder == null || empty($sortOrder))
            $sortOrder = ['displayGroup'];

        $entries = [];
        $params = [];

        $select = '
            SELECT `displaygroup`.displayGroupId,
                `displaygroup`.displayGroup,
                `displaygroup`.isDisplaySpecific,
                `displaygroup`.description,
                `displaygroup`.isDynamic,
                `displaygroup`.dynamicCriteria,
                `displaygroup`.userId,
                `lkdgdgjoin`.parentId,
                `lkdgdgjoin`.childId,
                `lkdgdgjoin`.depth,
                (select count(*) from `lkdgdg` where `lkdgdg`.parentId = `lkdgdgjoin`.childId and `lkdgdg`.depth <> 0) as subdgchildcount,
                (select count(*) from `lkdisplaydg` where `lkdisplaydg`.DisplayGroupID = `displaygroup`.displayGroupId) as displaycount
        ';

        $body = '
              FROM `displaygroup`
              INNER JOIN (SELECT childId, parentId, max(depth) depth 
                            FROM lkdgdg where (depth = 0 or depth = 1) group by childId 
                            ) lkdgdgjoin
                ON lkdgdgjoin.childId = `displaygroup`.displayGroupId
        ';

        if ($this->getSanitizer()->getInt('mediaId', $filterBy) !== null) {
            $body .= '
                INNER JOIN lkmediadisplaygroup
                ON lkmediadisplaygroup.displayGroupId = `displaygroup`.displayGroupId
                    AND lkmediadisplaygroup.mediaId = :mediaId
            ';
            $params['mediaId'] = $this->getSanitizer()->getInt('mediaId', $filterBy);
        }

        if ($this->getSanitizer()->getInt('eventId', $filterBy) !== null) {
            $body .= '
                INNER JOIN `lkscheduledisplaygroup`
                ON `lkscheduledisplaygroup`.displayGroupId = `displaygroup`.displayGroupId
                    AND `lkscheduledisplaygroup`.eventId = :eventId
            ';
            $params['eventId'] = $this->getSanitizer()->getInt('eventId', $filterBy);
        }

        $body .= ' WHERE 1 = 1 ';

        // View Permissions
        $this->viewPermissionSql('Xibo\Entity\DisplayGroup', $body, $params, '`displaygroup`.displayGroupId', '`displaygroup`.userId', $filterBy);

        if ($this->getSanitizer()->getInt('displayGroupId', $filterBy) !== null) 
        {
            $body .= ' AND displaygroup.displayGroupId = :displayGroupId ';
            $params['displayGroupId'] = $this->getSanitizer()->getInt('displayGroupId', $filterBy);
        }

        if ($this->getSanitizer()->getInt('parentId', $filterBy) !== null) {
            $body .= ' AND `displaygroup`.displayGroupId IN (SELECT `childId` FROM `lkdgdg` WHERE `parentId` = :parentId AND `depth` = 1) ';
            $params['parentId'] = $this->getSanitizer()->getInt('parentId', $filterBy);
        }

        if ($this->getSanitizer()->getInt('isDisplaySpecific', 0, $filterBy) != -1) {
            $body .= ' AND displaygroup.isDisplaySpecific = :isDisplaySpecific ';
            $params['isDisplaySpecific'] = $this->getSanitizer()->getInt('isDisplaySpecific', 0, $filterBy);
        }

        if ($this->getSanitizer()->getInt('isDynamic', $filterBy) !== null) {
            $body .= ' AND `displaygroup`.isDynamic = :isDynamic ';
            $params['isDynamic'] = $this->getSanitizer()->getInt('isDynamic', $filterBy);
        }

        if ($this->getSanitizer()->getString('dynamicCriteria', $filterBy) !== null) {
            $body .= ' AND `displaygroup`.dynamicCriteria = :dynamicCriteria ';
            $params['dynamicCriteria'] = $this->getSanitizer()->getString('dynamicCriteria', $filterBy);
        }

        if ($this->getSanitizer()->getInt('displayId', $filterBy) !== null) {
            $body .= ' AND displaygroup.displayGroupId IN (SELECT displayGroupId FROM lkdisplaydg WHERE displayId = :displayId) ';
            $params['displayId'] = $this->getSanitizer()->getInt('displayId', $filterBy);
        }

        if ($this->getSanitizer()->getInt('nestedDisplayId', $filterBy) !== null) {
            $body .= ' 
                AND displaygroup.displayGroupId IN (
                    SELECT DISTINCT parentId
                      FROM `lkdgdg`
                        INNER JOIN `lkdisplaydg`
                        ON `lkdisplaydg`.displayGroupId = `lkdgdg`.childId 
                     WHERE displayId = :nestedDisplayId
                ) 
            ';
            $params['nestedDisplayId'] = $this->getSanitizer()->getInt('nestedDisplayId', $filterBy);
        }

        if ($this->getSanitizer()->getInt('notificationId', $filterBy) !== null) {
            $body .= ' AND displaygroup.displayGroupId IN (SELECT displayGroupId FROM `lknotificationdg` WHERE notificationId = :notificationId) ';
            $params['notificationId'] = $this->getSanitizer()->getInt('notificationId', $filterBy);
        }

        // Filter by DisplayGroup Name?
        if ($this->getSanitizer()->getString('displayGroup', $filterBy) != null) {
            // convert into a space delimited array
            $names = explode(' ', $this->getSanitizer()->getString('displayGroup', $filterBy));

            $i = 0;
            foreach ($names as $searchName) {
                $i++;
                // Not like, or like?
                if (substr($searchName, 0, 1) == '-') {
                    $body .= " AND  `displaygroup`.displayGroup NOT LIKE :search$i ";
                    $params['search' . $i] = '%' . ltrim(($searchName), '-') . '%';
                }
                else {
                    $body .= " AND  `displaygroup`.displayGroup LIKE :search$i ";
                    $params['search' . $i] = '%' . $searchName . '%';
                }
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

        //print($sql);

        foreach ($this->getStore()->select($sql, $params) as $row) {
            $entries[] = $this->createEmpty()->hydrate($row, ['intProperties' => ['isDisplaySpecific', 'isDynamic']]);
        }

        // Paging
        if ($limit != '' && count($entries) > 0) {
            $results = $this->getStore()->select('SELECT COUNT(*) AS total ' . $body, $params);
            $this->_countLast = intval($results[0]['total']);
        }

        return $entries;
    }
}