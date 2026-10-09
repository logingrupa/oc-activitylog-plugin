<?php

declare(strict_types=1);

namespace Logingrupa\Activitylog\Tests;

use Illuminate\Auth\AuthManager;
use Illuminate\Support\Env;
use Illuminate\Support\Facades\Config;
use Logingrupa\Activitylog\Models\Activity;
use Logingrupa\Activitylog\Plugin;
use Logingrupa\Activitylog\Tests\Fixtures\Models\FixtureNote;
use RainLab\User\Classes\AuthManager as RainLabAuthManager;
use Spatie\Activitylog\Support\CauserResolver;
use Spatie\Activitylog\Support\Config as ActivitylogConfig;

/**
 * The plugin registers three things the package needs before it can write a row. phpunit.xml sets the buffer
 * switch to true, so these cases run under the value the plugin has to overrule.
 */
final class PluginBootTest extends ActivitylogTestCase
{
    /**
     * testTheAuthAliasResolvesRainLabsManagerAndTheResolverBuilds builds the causer resolver, which type-hints Illuminate's AuthManager.
     */
    public function testTheAuthAliasResolvesRainLabsManagerAndTheResolverBuilds(): void
    {
        $obManager = $this->app->make(AuthManager::class);

        $this->assertSame($this->app->make('auth'), $obManager);
        $this->assertInstanceOf(RainLabAuthManager::class, $obManager);
        $this->assertInstanceOf(CauserResolver::class, $this->app->make(CauserResolver::class));
    }

    /**
     * testThePackageWritesRowsThroughThePluginModel reads the model the package would instantiate.
     */
    public function testThePackageWritesRowsThroughThePluginModel(): void
    {
        $this->assertSame(Activity::class, ActivitylogConfig::activityModel());
    }

    /**
     * testBufferedLoggingStaysOffWhenTheEnvironmentTurnsItOn compares the environment the config reads with the config the plugin leaves.
     */
    public function testBufferedLoggingStaysOffWhenTheEnvironmentTurnsItOn(): void
    {
        $this->assertTrue(Env::get('ACTIVITYLOG_BUFFER_ENABLED'));
        $this->assertFalse(Config::get('activitylog.buffer.enabled'));
    }

    /**
     * testAConsumersModelSurvivesASecondRegistration sets a model the guard would never accept as a default and registers the plugin again.
     */
    public function testAConsumersModelSurvivesASecondRegistration(): void
    {
        Config::set('activitylog.activity_model', FixtureNote::class);

        (new Plugin($this->app))->register();

        $this->assertSame(FixtureNote::class, Config::get('activitylog.activity_model'));
    }
}
