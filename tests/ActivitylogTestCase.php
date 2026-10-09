<?php

declare(strict_types=1);

namespace Logingrupa\Activitylog\Tests;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Auth;
use PluginTestCase;
use RainLab\User\Models\User;

/**
 * Base class for the Activitylog tests. PostgreSQL only: the suite refuses to run against any database whose name does not end in _testing,
 * and each test runs inside a transaction.
 */
abstract class ActivitylogTestCase extends PluginTestCase
{
    private const TESTING_CONNECTION = 'pgsql';

    private const TESTING_SUFFIX = '_testing';

    private const LOCAL_HOSTS = ['127.0.0.1', 'localhost'];

    private const PASSWORD = 'ChangeMe888';

    protected $useTransactions = true;

    /**
     * createApplication guards the configured and the live connection.
     *
     * @return Application
     *
     * @throws \RuntimeException when either connection is not a local PostgreSQL testing database.
     */
    #[\Override]
    public function createApplication(): Application
    {
        $obApplication = parent::createApplication();
        if (!$obApplication instanceof Application) {
            throw new \UnexpectedValueException('PluginTestCase::createApplication() did not return an Illuminate application.');
        }

        self::assertConfiguredConnection($obApplication->make(Repository::class));
        self::assertLiveConnection($obApplication->make(DatabaseManager::class));

        return $obApplication;
    }

    /**
     * setUp turns lazy loading into an error, so a test cannot hide a query behind a property read.
     */
    #[\Override]
    public function setUp(): void
    {
        parent::setUp();

        Model::preventLazyLoading();
    }

    /**
     * signIn makes the user the web guard's user.
     */
    protected function signIn(User $obUser): void
    {
        Auth::forgetGuards();
        Auth::login($obUser);
    }

    /**
     * createUser creates a frontend user through the model.
     */
    protected function createUser(string $sEmail): User
    {
        $obUser = User::create([
            'first_name' => 'Writer',
            'username' => $sEmail,
            'email' => $sEmail,
            'password' => self::PASSWORD,
            'password_confirmation' => self::PASSWORD,
        ]);
        if (!$obUser instanceof User) {
            throw new \UnexpectedValueException('User::create() did not return a user');
        }

        return $obUser;
    }

    /**
     * thrownBy runs the callback and returns what it threw, or null when it finished.
     */
    protected function thrownBy(\Closure $fnAction): ?\Throwable
    {
        try {
            $fnAction();
        } catch (\Throwable $obThrowable) {
            return $obThrowable;
        }

        return null;
    }

    /**
     * assertConfiguredConnection checks the default connection named in the configuration.
     *
     * @throws \RuntimeException when the configured connection is not a local PostgreSQL testing database.
     */
    private static function assertConfiguredConnection(Repository $obConfig): void
    {
        $mConnectionName = $obConfig->get('database.default');
        if (!is_string($mConnectionName)) {
            throw new \RuntimeException('Refusing to run: database.default is not a connection name');
        }

        $mConnectionConfig = $obConfig->get('database.connections.'.$mConnectionName);
        $arConnectionConfig = is_array($mConnectionConfig) ? $mConnectionConfig : [];

        self::assertTestingConnection(
            $mConnectionName,
            self::stringOrNull($arConnectionConfig['database'] ?? null),
            self::stringOrNull($arConnectionConfig['host'] ?? null),
        );
    }

    /**
     * assertLiveConnection checks the connection the application actually opened.
     *
     * @throws \RuntimeException when the live connection is not a local PostgreSQL testing database.
     */
    private static function assertLiveConnection(DatabaseManager $obDatabase): void
    {
        $obLiveConnection = $obDatabase->connection();

        self::assertTestingConnection(
            $obLiveConnection->getName() ?? 'unknown',
            $obLiveConnection->getDatabaseName(),
            self::stringOrNull($obLiveConnection->getConfig('host')),
        );
    }

    /**
     * assertTestingConnection throws unless the connection is a local PostgreSQL database whose name ends in _testing.
     *
     * @throws \RuntimeException naming the connection, database and host it got.
     */
    private static function assertTestingConnection(string $sConnection, ?string $sDatabase, ?string $sHost): void
    {
        $bSafe = $sConnection === self::TESTING_CONNECTION
            && $sDatabase !== null
            && str_ends_with($sDatabase, self::TESTING_SUFFIX)
            && in_array($sHost, self::LOCAL_HOSTS, true);
        if ($bSafe) {
            return;
        }

        throw new \RuntimeException(sprintf(
            'Refusing to run: expected a local %s database whose name ends in %s, got %s/%s on %s',
            self::TESTING_CONNECTION,
            self::TESTING_SUFFIX,
            $sConnection,
            $sDatabase ?? 'null',
            $sHost ?? 'null',
        ));
    }

    /**
     * stringOrNull narrows a configuration value to a string, or null when it is anything else.
     */
    private static function stringOrNull(mixed $mValue): ?string
    {
        return is_string($mValue) ? $mValue : null;
    }
}
