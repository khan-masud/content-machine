<?php

namespace App\Providers;

use App\Contracts\FacebookPageClient;
use App\Contracts\ImageGenerator;
use App\Contracts\PexelsProvider;
use App\Contracts\PexelsProviderFactory;
use App\Contracts\PhotoCardRenderer;
use App\Support\AiProviders\AiCompletionClientContract;
use App\Support\AiProviders\AiProviderVerifierContract;
use App\Support\AiProviders\AiTranscriptionClientContract;
use App\Support\AiProviders\AiVisionCompletionClientContract;
use App\Support\AiProviders\HttpAiCompletionClient;
use App\Support\AiProviders\HttpAiProviderVerifier;
use App\Support\AiProviders\OpenAiTranscriptionClient;
use App\Support\CurrentApiToken;
use App\Support\CurrentWorkspace;
use App\Support\Facebook\HttpFacebookPageClient;
use App\Support\ImageAi\CloudflareImageGenerator;
use App\Support\ImageAi\ImageGeneratorChain;
use App\Support\ImageAi\PollinationsImageGenerator;
use App\Support\LinkResolution\LinkResolverContract;
use App\Support\LinkResolution\ProcessLinkResolver;
use App\Support\Pexels\HttpPexelsProvider;
use App\Support\Pexels\HttpPexelsProviderFactory;
use App\Support\Pexels\PexelsConfig;
use App\Support\PhotoCards\Backgrounds\AiCardBackgroundSource;
use App\Support\PhotoCards\Backgrounds\CardBackgroundSources;
use App\Support\PhotoCards\Backgrounds\LinkCardBackgroundSource;
use App\Support\PhotoCards\Backgrounds\PexelsCardBackgroundSource;
use App\Support\PhotoCards\PillowPhotoCardRenderer;
use App\Support\Telegram\HttpTelegramClient;
use App\Support\Telegram\TelegramClientContract;
use App\Support\Telegram\TelegramPhotoSenderContract;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Middleware\RedirectIfAuthenticated;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(CurrentWorkspace::class);
        $this->app->singleton(CurrentApiToken::class);
        $this->app->bind(LinkResolverContract::class, ProcessLinkResolver::class);
        $this->app->bind(AiProviderVerifierContract::class, HttpAiProviderVerifier::class);
        $this->app->bind(AiTranscriptionClientContract::class, OpenAiTranscriptionClient::class);
        $this->app->bind(AiCompletionClientContract::class, HttpAiCompletionClient::class);
        $this->app->bind(AiVisionCompletionClientContract::class, HttpAiCompletionClient::class);
        $this->app->bind(TelegramClientContract::class, HttpTelegramClient::class);
        $this->app->bind(TelegramPhotoSenderContract::class, HttpTelegramClient::class);
        $this->app->bind(PhotoCardRenderer::class, PillowPhotoCardRenderer::class);
        $this->app->bind(PexelsProviderFactory::class, HttpPexelsProviderFactory::class);
        $this->app->bind(FacebookPageClient::class, HttpFacebookPageClient::class);
        $this->app->bind(ImageGenerator::class, fn ($app): ImageGenerator => new ImageGeneratorChain([
            $app->make(CloudflareImageGenerator::class),
            $app->make(PollinationsImageGenerator::class),
        ]));
        $this->app->bind(CardBackgroundSources::class, fn ($app): CardBackgroundSources => new CardBackgroundSources([
            'ai' => $app->make(AiCardBackgroundSource::class),
            'link' => $app->make(LinkCardBackgroundSource::class),
            'pexels' => $app->make(PexelsCardBackgroundSource::class),
        ]));
        $this->app->bind(PexelsProvider::class, function ($app): PexelsProvider {
            $workspace = $app->make(CurrentWorkspace::class)->get();
            abort_if($workspace === null, 404, 'No current workspace.');

            return new HttpPexelsProvider(PexelsConfig::fromWorkspace($workspace));
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();

        // App\Listeners\CreatePersonalTeamOnRegistration (on Registered) and
        // App\Listeners\AcceptPendingTeamInvitationOnLogin (on Login) are
        // NOT registered here: Laravel's event auto-discovery already wires
        // them up from their handle() method type-hints. Registering them
        // again here double-fires every listener in app/Listeners on every
        // matching event (confirmed via `php artisan event:list` during
        // manual testing: a single registration created two personal teams).

        // Laravel's default RedirectIfAuthenticated ("guest" middleware,
        // guarding /login and /register) looks for a route literally named
        // "dashboard" and falls back to "home" when it doesn't find one.
        // This app's dashboard home route is named "dashboard.home" (every
        // dashboard.php route is prefixed), never bare "dashboard", so an
        // already-authenticated user hitting /login was silently bounced to
        // the marketing homepage instead of the dashboard. Confirmed live
        // 2026-08-20: this is exactly what locked Harun out after his first
        // successful login.
        RedirectIfAuthenticated::redirectUsing(fn () => route('dashboard.home'));

        // Railway terminates TLS before the app container. Pin absolute URL
        // generation to the operator-controlled APP_URL instead of trusting
        // spoofable forwarded host, port, or scheme headers from any proxy.
        if (app()->environment('production', 'prod')) {
            $appUrl = rtrim((string) config('app.url'), '/');

            URL::forceRootUrl($appUrl);
            URL::forceScheme(parse_url($appUrl, PHP_URL_SCHEME) ?: 'https');
        }
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
