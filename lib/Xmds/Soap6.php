<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (Soap5.php)
 */


namespace Xibo\Xmds;


use Xibo\Entity\Bandwidth;
use Xibo\Entity\Display;
use Xibo\Exception\NotFoundException;

class Soap6 extends Soap5
{
    /**
     * Gets additional resources for assigned media
     * @param string $serverKey
     * @param string $hardwareKey
     * @param int $layoutId
     * @param string $regionId
     * @param string $mediaId
     * @return mixed
     * @throws \SoapFault
     */
    function GetResource($serverKey, $hardwareKey, $layoutId, $preferredDisplayWidth, $preferredDisplayHeight, $mediaId)
    {
        return $this->doGetResource($serverKey, $hardwareKey, $layoutId, $regionId, $mediaId);
    }
}