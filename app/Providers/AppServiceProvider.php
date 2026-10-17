<?php

namespace App\Providers;

use App\Models\Setting;
use App\Support\Greeting;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Everything that asks for the SMS service gets whichever gateway SMS_PROVIDER names
        // (SpesoSmsService extends PastechSmsService, so existing type-hints keep working).
        $this->app->bind(
            \App\Services\PastechSmsService::class,
            fn () => config('services.sms_provider') === 'speso'
                ? new \App\Services\SpesoSmsService()
                : new \App\Services\PastechSmsService()
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Staff password-reset email, in the school's name rather than the framework default.
        ResetPassword::toMailUsing(function ($user, string $token) {
            $school = Setting::get('institution_name', config('app.name'));
            $url = route('password.reset', ['token' => $token, 'email' => $user->getEmailForPasswordReset()]);

            return (new MailMessage)
                ->subject("Reset your {$school} portal password")
                ->greeting('Hello ' . Greeting::shortName($user->name) . ',')
                ->line('We received a request to reset the password for your staff account.')
                ->action('Choose a new password', $url)
                ->line('This link expires in 60 minutes. If you did not ask for a reset, you can ignore this email — your password will not change.')
                ->salutation("— {$school}");
        });
    }
}
