<?php
/*

 */
use Xibo\Service\ConfigService;

DEFINE('XIBO', true);
define('PROJECT_ROOT', realpath(__DIR__ . '/..'));

error_reporting(E_ALL);
ini_set('display_errors', E_ALL);

require PROJECT_ROOT . '/vendor/autoload.php';

if (!file_exists('settings.php')) 
{
    {
        die('Not configured');
    }
}
$uidProcessor = new \Monolog\Processor\UidProcessor(7);
// Create a logger
$logger = new \Xibo\Helper\AccessibleMonologWriter(array(
    'name' => 'XMDSHANDLER',
    'handlers' => [
        new \Xibo\Helper\DatabaseLogHandler()
    ],
    'processors' => array(
        new \Xibo\Helper\LogProcessor(),
        new \Monolog\Processor\UidProcessor(7)
    )
), false);

// Slim Application
$app = new \RKA\Slim(array(
    'debug' => true,
    'log.writer' => $logger
));
$app->setName('xmds');

// Load the config
$app->configService = \Xibo\Service\ConfigService::Load(PROJECT_ROOT . '/web/settings.php');

// Set storage
\Xibo\Middleware\Storage::setStorage($app->container);
// Set state
\Xibo\Middleware\State::setState($app);
//$app->add(new \Xibo\Middleware\State());
$app->add(new \Xibo\Middleware\Storage());

// Handle additional Middleware
\Xibo\Middleware\State::setMiddleWare($app);

// Torn down all logging
$app->getLog()->setLevel(\Xibo\Service\LogService::resolveLogLevel('error'));
// We need a View for rendering GetResource Templates
// Twig templates
$twig = new \Slim\Views\Twig();
$twig->parserOptions = array(
    'debug' => true,
    'cache' => PROJECT_ROOT . '/cache'
);
$twig->parserExtensions = array(
    new \Slim\Views\TwigExtension(),
    new \Xibo\Twig\TransExtension(),
    new \Xibo\Twig\UrlDecodeTwigExtension()
);

// Configure a user
$app->user = $app->userFactory->getById(1);

// Configure the Slim error handler
$app->error(function (\Exception $e) use ($app) {
    $app->container->get('\Xibo\Controller\Error')->handler($e);
});

// Configure a not found handler
$app->notFound(function () use ($app) {
    $app->container->get('\Xibo\Controller\Error')->notFound();
});
// Configure the template folder
$twig->twigTemplateDirs = array_merge($app->moduleFactory->getViewPaths(), [PROJECT_ROOT . '/views']);
$app->view($twig);

$logProcessor = new \Xibo\Xmds\LogProcessor($app->getLog(), $uidProcessor->getUid());
$app->logWriter->addProcessor($logProcessor);
//$app->container->singleton('\Xibo\Controller\XMDSHandler')->SetupSubDependency($logProcessor);
// Check to see if we have a file attribute set (for HTTP file downloads)
if (isset($_GET['file'])) 
{
    // Check send file mode is enabled
    $sendFileMode = $app->configService->GetSetting('SENDFILE_MODE');

    if ($sendFileMode == 'Off') {
        $app->logService->notice('HTTP GetFile request received but SendFile Mode is Off. Issuing 404', 'services');
        header('HTTP/1.0 404 Not Found');
        exit;
    }

    // Check nonce, output appropriate headers, log bandwidth and stop.
    try {
        /** @var \Xibo\Entity\RequiredFile $file */
        $file = $app->requiredFileFactory->getByNonce($_REQUEST['file']);

        // Add the size to the bytes we have already requested.
        $file->bytesRequested = $file->bytesRequested + $file->size;

        // Check the file is valid
        $file->isValid();

        // Only log bandwidth under certain conditions
        // also controls whether the nonce is updated
        $logBandwidth = true;

        // Are we a DELETE request or otherwise?
        if ($_SERVER['REQUEST_METHOD'] == 'HEAD') {
            // Supply a header only, pointing to the original file name
            header('Content-Disposition: attachment; filename="' . $file->storedAs . '"');
            $logBandwidth = false;

        } else if ($_SERVER['REQUEST_METHOD'] == 'DELETE') {
            // Log bandwidth for the file being requested
            $app->logService->info('Delete request for ' . $file->storedAs . ' marking nonce as used.', $file->displayId);

        } else {
            // Most likely a Get Request
            // Issue magic packet
            $app->logService->info('HTTP GetFile request redirecting to ' . $app->configService->GetSetting('LIBRARY_LOCATION') . $file->storedAs, 'services');

            // Send via Apache X-Sendfile header?
            if ($sendFileMode == 'Apache') {
                header('X-Sendfile: ' . $app->configService->GetSetting('LIBRARY_LOCATION') . $file->storedAs);
            } // Send via Nginx X-Accel-Redirect?
            else if ($sendFileMode == 'Nginx') {
                header('X-Accel-Redirect: /download/' . $file->storedAs);
            } else {
                header('HTTP/1.0 404 Not Found');
            }
        }

        // Log bandwidth
        if ($logBandwidth) {
            $file->markUsed();
            $app->bandwidthFactory->createAndSave(4, $file->displayId, $file->size);
        }
    }
    catch (\Exception $e) {
        if ($e instanceof \Xibo\Exception\NotFoundException || $e instanceof \Xibo\Exception\FormExpiredException) {
            $app->logService->notice('HTTP GetFile request received but unable to find XMDS Nonce. Issuing 404', 'services');
            // 404
            header('HTTP/1.0 404 Not Found');
        }
        else
            throw $e;
    }

    if ($app->store->getConnection()->inTransaction())
        $app->store->getConnection()->commit();

    exit;
}
// All application routes
require PROJECT_ROOT . '/lib/routes-xmds.php';

// Run App
try {
    $app->run();
}
catch (Exception $e) {
   $app->logService->error($e->getMessage());

    header('HTTP/1.1 500 Internal Server Error');
    header('Content-Type: text/plain');
    die (__('There has been an unknown error with XMDS, it has been logged. Please contact your administrator.'));
}
