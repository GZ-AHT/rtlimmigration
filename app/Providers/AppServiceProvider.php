<?php

namespace App\Providers;

use App\Enums\Role;
use App\Models\User;
use App\Policies\RolePolicy;
use App\Support\Chat\AutoReply\KeywordReplyProvider;
use App\Support\Chat\AutoReply\ReplyProvider;
use App\Support\Chat\Security\ClamAvScanner;
use App\Support\Chat\Security\MalwareScanner;
use App\Support\Chat\Security\NullMalwareScanner;
use App\Support\Search\GlobalSearch;
use App\Support\Search\Providers\PageSearchProvider;
use App\Support\Search\Providers\UserSearchProvider;
use App\Support\Settings\SettingsRepository;
use App\Support\Webhooks\Webhooks;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Spatie\Permission\Models\Role as RoleModel;
use Throwable;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(SettingsRepository::class);

        // Automatic chat replies: swap this binding for an AI-powered provider later.
        $this->app->bind(ReplyProvider::class, KeywordReplyProvider::class);

        // Chat attachment virus scanning (config/chat.php).
        $this->app->bind(MalwareScanner::class, fn () => config('chat.malware.driver') === 'clamav'
            ? new ClamAvScanner((string) config('chat.malware.clamav.socket'), (int) config('chat.malware.clamav.timeout'))
            : new NullMalwareScanner);

        // Global search modules, in display order. Later phases add theirs.
        $this->app->singleton(GlobalSearch::class, fn ($app) => new GlobalSearch([
            $app->make(PageSearchProvider::class),
            $app->make(UserSearchProvider::class),
        ]));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        Webhooks::listen();
        $this->configureAuthorization();
    }

    /**
     * Super Admin can access and control all modules (REQ §27, rule 1).
     * Returning null for everyone else falls through to normal checks.
     */
    protected function configureAuthorization(): void
    {
        Gate::before(fn (User $user): ?bool => $user->hasRole(Role::SuperAdmin->value) ? true : null);

        Gate::policy(RoleModel::class, RolePolicy::class);
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        // REQ §23.2 password policy: System → Settings → Security. Until Super Admin
        // saves a policy, production keeps the strict built-in rule.
        Password::defaults(function (): ?Password {
            try {
                $settings = app(SettingsRepository::class);
                $min = $settings->get('security.password_min_length');
            } catch (Throwable) {
                $min = null;
            }

            if ($min === null) {
                return app()->isProduction()
                    ? Password::min(12)->mixedCase()->letters()->numbers()->symbols()->uncompromised()
                    : null;
            }

            $rule = Password::min((int) $min);

            if ($settings->get('security.password_mixed_case')) {
                $rule->mixedCase();
            }
            if ($settings->get('security.password_numbers')) {
                $rule->numbers();
            }
            if ($settings->get('security.password_symbols')) {
                $rule->symbols();
            }
            if ($settings->get('security.password_uncompromised')) {
                $rule->uncompromised();
            }

            return $rule;
        });

        // Idle sign-out time from the same screen (config is read before the session starts).
        try {
            $lifetime = app(SettingsRepository::class)->get('security.session_lifetime');

            if (is_numeric($lifetime)) {
                config(['session.lifetime' => (int) $lifetime]);
            }
        } catch (Throwable) {
            // Settings table not migrated yet.
        }
    }
}
