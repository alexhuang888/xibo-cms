<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (ItemCreatorFactory.php)
 */


namespace Xibo\Factory;


use Xibo\Entity\DisplayGroup;
use Xibo\Entity\User;
use Xibo\Exception\NotFoundException;
use Xibo\Service\LogServiceInterface;
use Xibo\Service\SanitizerServiceInterface;
use Xibo\Storage\StorageServiceInterface;
require_once PROJECT_ROOT . '/lib/Helper/ItemIDDef.php';
/**
 * Class ItemCreatorFactory
 * @package Xibo\Factory
 */
class ItemCreatorFactory extends BaseFactory
{
    /**
     * @var PermissionFactory
     */
    private $permissionFactory;
    private $displaygroupFactory;
    private $container;
    /**
     * Construct a factory
     * @param StorageServiceInterface $store
     * @param LogServiceInterface $log
     * @param SanitizerServiceInterface $sanitizerService
     * @param User $user
     * @param UserFactory $userFactory
     * @param PermissionFactory $permissionFactory
     */
    public function __construct($store, $log, $sanitizerService, $user, $userFactory, $permissionFactory,
                                    $container)
    {
        $this->setCommonDependencies($store, $log, $sanitizerService);
        $this->setAclDependencies($user, $userFactory);

        $this->permissionFactory = $permissionFactory;
        $this->container = $container;
    }

    /**
     * Create Empty
     * @return DisplayGroup
     */
    public function createByItemID($itemtype, $itemid)
    {
        if ($itemtype == \Xibo\Entity\DisplayGroup::ItemType()) // display DisplayGroup
        {
            return $this->container->displayGroupFactory->getById($itemid);
        }
        if ($itemtype == \Xibo\Entity\Display::ItemType()) // display 
        {
            return $this->container->displayFactory->getById($itemid);
        }     
        if ($itemtype == \Xibo\Entity\Media::ItemType()) // media 
        {
            return $this->container->mediaFactory->getById($itemid);
        } 
        if ($itemtype == \Xibo\Entity\Playlist::ItemType()) // Playlist 
        {
            return $this->container->playlistFactory->getById($itemid);
        }                
    }

    
}