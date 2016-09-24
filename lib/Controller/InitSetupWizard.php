<?php
namespace Xibo\Controller;

use Xibo\Entity\Display;
use Xibo\Exception\AccessDeniedException;
use Xibo\Exception\ConfigurationException;
use Xibo\Exception\NotFoundException;
use Xibo\Factory\CommandFactory;
use Xibo\Factory\DisplayFactory;
use Xibo\Factory\DisplayGroupFactory;
use Xibo\Factory\LayoutFactory;
use Xibo\Factory\MediaFactory;
use Xibo\Factory\ModuleFactory;
use Xibo\Factory\ScheduleFactory;
use Xibo\Factory\TagFactory;
use Xibo\Service\ConfigServiceInterface;
use Xibo\Service\DateServiceInterface;
use Xibo\Service\LogServiceInterface;
use Xibo\Service\PlayerActionServiceInterface;
use Xibo\Service\SanitizerServiceInterface;
use Xibo\XMR\ChangeLayoutAction;
use Xibo\XMR\CollectNowAction;
use Xibo\XMR\CommandAction;
use Xibo\XMR\OverlayLayoutAction;
use Xibo\XMR\RevertToSchedule;
require_once PROJECT_ROOT . '/lib/Helper/ItemIDDef.php';
/**
 * Class InitSetupWizard
 * @package Xibo\Controller
 * this class is to setup basic environments, including company profile, branch profiles, admin info,
 * default template info and dispaly join tokens.
 */
class InitSetupWizard extends Base
{
    /**
     * @var LayoutFactory
     */
    private $layoutFactory;

    /**
     * @var tagFactory
     */
    private $tagFactory;

    /**
     * Set common dependencies.
     * @param LogServiceInterface $log
     * @param SanitizerServiceInterface $sanitizerService
     * @param \Xibo\Helper\ApplicationState $state
     * @param \Xibo\Entity\User $user
     * @param \Xibo\Service\HelpServiceInterface $help
     * @param DateServiceInterface $date
     * @param ConfigServiceInterface $config
     * @param LayoutFactory $layoutFactory
     * @param TagFactory $tagFactory
     */
    public function __construct($log, $sanitizerService, $state, $user, $help, $date, $config, $layoutFactory,  
                                $tagFactory)
    {
        $this->setCommonDependencies($log, $sanitizerService, $state, $user, $help, $date, $config);

        $this->layoutFactory = $layoutFactory;
        $this->tagFactory = $tagFactory;
    }

    public function displayPage()
    {
        $config = $this->getConfig();
        $data = [
        ];

        $this->getState()->template = 'init-setup-wizard-page';
        $this->getState()->setData($data);        
    }
};

?>