<?php
// register display
$app->post('/xmds/system/registerdisplay', '\Xibo\Controller\XMDSHandler:RegisterDisplay');

// get required files
$app->post('/xmds/system/requiredfiles', '\Xibo\Controller\XMDSHandler:RequiredFiles');

// get file
$app->post('/xmds/system/getfile', '\Xibo\Controller\XMDSHandler:GetFile');

// get file
$app->post('/xmds/system/getschedule', '\Xibo\Controller\XMDSHandler:Schedule');

// blacklist media
$app->post('/xmds/system/blacklistmedia', '\Xibo\Controller\XMDSHandler:BlackList');

// submit log
$app->post('/xmds/system/submitlog', '\Xibo\Controller\XMDSHandler:SubmitLog');

// submit stats
$app->post('/xmds/system/submitstats', '\Xibo\Controller\XMDSHandler:SubmitStats');

// media inventory
$app->post('/xmds/system/mediainventory', '\Xibo\Controller\XMDSHandler:MediaInventory');

// get resource
$app->post('/xmds/system/getresource', '\Xibo\Controller\XMDSHandler:GetResource');

// notify status
$app->post('/xmds/system/notifystatus', '\Xibo\Controller\XMDSHandler:NotifyStatus');

// submit screenshot
$app->post('/xmds/system/submitscreenshot', '\Xibo\Controller\XMDSHandler:SubmitScreenShot');