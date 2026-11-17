<?php

namespace App\Providers;

use App\Enums\Role;
use App\Models\User;
use App\Policies\RolePolicy;
use App\Support\Chat\Security\ClamAvScanner;
use App\Support\Chat\Security\MalwareScanner;
use App\Support\Chat\Security\NullMalwareScanner;
use App\Support\Search\GlobalSearch;
use App\Support\Search\Providers\PageSearchProvider;
use App\Support\Search\Providers\UserSearchProvider;
use App\Support\Settings\SettingsRepository;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Spatie\Permission\Models\Role as RoleModel;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(SettingsRepository::class);

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

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
