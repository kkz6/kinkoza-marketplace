<?php

namespace App\Models;

use App\Models\Concerns\HasUlidAndSequence;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail as MustVerifyEmailContract;
use Illuminate\Contracts\Translation\HasLocalePreference;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;

#[Fillable([
    'name',
    'email',
    'password',
    'company_name',
    'registration_number',
    'phone',
    'country',
    'locale',
])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements HasLocalePreference, MustVerifyEmailContract, PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasUlidAndSequence, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'is_verified_seller' => 'boolean',
            'password' => 'hashed',
        ];
    }

    /**
     * Get the user's initials
     */
    public function initials(): string
    {
        $initials = Str::initials($this->name, true);

        return Str::length($initials) > 1
            ? Str::substr($initials, 0, 1).Str::substr($initials, -1)
            : $initials;
    }

    public function preferredLocale(): string
    {
        $locale = $this->getAttribute('locale');
        $supportedLocaleConfiguration = config('locales.supported', []);
        $fallbackLocale = config('app.fallback_locale', 'en');

        if (! is_array($supportedLocaleConfiguration) || ! is_string($fallbackLocale)) {
            return 'en';
        }

        $supportedLocales = array_keys($supportedLocaleConfiguration);

        return is_string($locale) && in_array($locale, $supportedLocales, true)
            ? $locale
            : $fallbackLocale;
    }
}
