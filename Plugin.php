<?php

declare(strict_types=1);

namespace Logingrupa\Activitylog;

use Illuminate\Auth\AuthManager;
use Illuminate\Support\Facades\Config;
use Logingrupa\Activitylog\Models\Activity;
use Spatie\Activitylog\Models\Activity as PackageActivity;
use System\Classes\PluginBase;

/**
 * Activity log plugin registration: the auth alias the package needs, the buffer switch pinned off and the activity model the package writes through.
 * It has no boot(): there is nothing to listen to or extend.
 */
class Plugin extends PluginBase
{
    /**
     * @var list<string> require lists the plugins migrated before this one. RainLab.User is named because the alias in register()
     * needs RainLab's AuthManager, the only Illuminate AuthManager an October install binds, and because a plugin's test process
     * registers only the entries listed here.
     */
    public $require = ['RainLab.User'];

    /**
     * pluginDetails about this plugin.
     *
     * @return array{name: string, description: string, author: string, icon: string}
     */
    #[\Override]
    public function pluginDetails(): array
    {
        return [
            'name' => 'logingrupa.activitylog::lang.plugin.name',
            'description' => 'logingrupa.activitylog::lang.plugin.description',
            'author' => 'Logingrupa',
            'icon' => 'icon-history',
        ];
    }

    /**
     * register aliases the auth service, switches off buffered logging and names the plugin model as the activity model.
     * Buffering stays off for every consumer because its bulk insert writes a second timestamp column that this table does not have.
     */
    #[\Override]
    public function register(): void
    {
        $this->aliasAuthManager();
        Config::set('activitylog.buffer.enabled', false);
        $this->nameActivityModel();
    }

    /**
     * aliasAuthManager lets the container build the package's causer resolver, which type-hints Illuminate's AuthManager.
     * October keeps the core alias for that class switched off and RainLab aliases only its own subclass.
     * RainLab's manager extends the Illuminate one, so the alias resolves the same singleton as the auth service.
     */
    private function aliasAuthManager(): void
    {
        $this->app->alias('auth', AuthManager::class);
    }

    /**
     * nameActivityModel replaces the package's own model while the config still holds it. The package merges its config before any
     * plugin registers, and a plugin that extends the model may already have named a subclass, so a value that is not the package
     * default stays: the consumer wins in whatever order the plugins register.
     */
    private function nameActivityModel(): void
    {
        if (Config::get('activitylog.activity_model') !== PackageActivity::class) {
            return;
        }

        Config::set('activitylog.activity_model', Activity::class);
    }
}
