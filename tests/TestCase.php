<?php

namespace Helio\Test;

use Doctrine\DBAL\Types\Type;
use Helio\Invest\Model\Filter\DeletedFilter;
use Helio\Invest\Model\Type\UTCDateTimeType;
use Helio\Invest\Model\User;
use Helio\Invest\Utility\JwtUtility;
use Helio\Invest\Utility\ServerUtility;
use Helio\Test\Infrastructure\App;
use Helio\Test\Infrastructure\Helper\DbHelper;
use Helio\Test\Infrastructure\Helper\ZapierHelper;
use Psr\Http\Message\ResponseInterface;
use Slim\Http\Request;
use Slim\Http\Environment;
use Doctrine\Common\Persistence\ObjectRepository;
use Webfactory\Doctrine\ORMTestInfrastructure\ORMInfrastructure;

/**
 * Class TestCase serves as root for all cases
 *
 * @package    Helio\Test\Functional
 * @author    Christoph Buchli <team@opencomputing.cloud>
 */
class TestCase extends \PHPUnit_Framework_TestCase
{
    /**
     * @var ORMInfrastructure
     */
    protected $infrastructure;


    /**
     * @var ObjectRepository
     */
    protected $userRepository;


    /**
     * @var ObjectRepository
     */
    protected $instanceRepository;


    /**
     * @var ObjectRepository
     */
    protected $jobRepository;


    /**
     * @var ObjectRepository
     */
    protected $taskRepository;


    /** @see \PHPUnit_Framework_TestCase::setUp()
     * @throws \Exception
     */
    protected function setUp()
    {
        $_SERVER['JWT_SECRET'] = 'ladida';
        $_SERVER['ZAPIER_HOOK_URL'] = '/blah';
        $_SERVER['SCRIPT_HASH'] = 'TESTSHA1';
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $_SERVER['SITE_ENV'] = 'TEST';


        // re-init Zapier helper to make sure no Responses are left in the stack etc.
        ZapierHelper::reset();


        // re-init DBHelper
        DbHelper::reset();
        Type::overrideType('datetime', UTCDateTimeType::class);
        Type::overrideType('datetimetz', UTCDateTimeType::class);


        $this->infrastructure = ORMInfrastructure::createWithDependenciesFor([User::class, Instance::class, Job::class, Task::class]);
        $this->infrastructure->getEntityManager()->getConfiguration()->addFilter('deleted', DeletedFilter::class);
        DbHelper::setInfrastructure($this->infrastructure);

        $this->userRepository = $this->infrastructure->getRepository(User::class);
        $this->instanceRepository = $this->infrastructure->getRepository(Instance::class);
        $this->jobRepository = $this->infrastructure->getRepository(Job::class);
        $this->taskRepository = $this->infrastructure->getRepository(Task::class);
    }

    /**
     *
     */
    public static function setUpBeforeClass()
    {
        if (!\defined('APPLICATION_ROOT')) {
            \define('APPLICATION_ROOT', \dirname(__DIR__));
            \define('LOG_DEST', APPLICATION_ROOT . '/log/app-test.log');
            \define('LOG_LVL', 100);
        }

        // make sure no shell commands are being executed.
        ServerUtility::setTesting();
    }


    /**
     * @param $dir
     *
     */
    public static function tearDownAfterClass($dir = APPLICATION_ROOT . '/tmp/cache/test'): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $files = array_diff(scandir($dir, 0), array('.', '..'));
        foreach ($files as $file) {
            is_dir("$dir/$file") ? self::tearDownAfterClass("$dir/$file") : unlink("$dir/$file");
        }

        rmdir($dir);
    }


    /**
     * Process the application given a request method and URI
     *
     * @param string $requestMethod the request method (e.g. GET, POST, etc.)
     * @param string $requestUri the request URI
     * @param bool $withMiddleware whether the app should include the middlewares (mainly JWT).
     * @param mixed $cookieData the request data
     * @param mixed $requestData the request data
     * @param array $attributes
     * @param bool|\Helio\Panel\App|App|null $app if set, this variable will contain the app for further analysis of
     *     results and processings
     *     (memory heavy!)
     *
     * @return ResponseInterface
     *
     * @throws \Exception
     */
    protected function runApp(
        $requestMethod,
        $requestUri,
        $withMiddleware = false,
        $cookieData = null,
        $requestData = null,
        array $attributes = [],
        &$app = null
    ): ResponseInterface
    {
        // Create a mock environment for testing with
        $environment = Environment::mock(
            [
                'REQUEST_METHOD' => $requestMethod,
                'REQUEST_URI' => $requestUri,
                'QUERY_STRING' => $requestParts[1] ?? ''
            ]
        );

        if ($cookieData) {
            $environment->set('HTTP_Cookie', $cookieData);
        }

        // Set up a request object based on the environment
        $request = Request::createFromEnvironment($environment);

        // Add request data, if it exists
        if ($requestData !== null) {
            $request = $request->withParsedBody($requestData);
        }

        if ($attributes) {
            $request = $request->withAttributes($attributes);
        }

        $middlewares = $withMiddleware ? [JwtUtility::class] : [];
        $app = App::getApp('test', $request, $middlewares);

        return $app->run(true);
    }
}