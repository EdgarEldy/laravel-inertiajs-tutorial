<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Jetstream\HasProfilePhoto;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens;

    /** @use HasFactory<UserFactory> */
    use HasFactory;

    use HasProfilePhoto;
    use Notifiable;
    use TwoFactorAuthenticatable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'telephone',
        'address',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_recovery_codes',
        'two_factor_secret',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array<int, string>
     */
    protected $appends = [
        'profile_photo_url',
        'name',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * `name` is not a real column - Jetstream's own registration/profile
     * forms still collect a single name field, but this project stores
     * `first_name`/`last_name` separately instead. This accessor is what
     * keeps every place Jetstream's own code reads `$user->name` (or the
     * frontend reads a serialized `name` prop) working unmodified: it
     * recombines the two columns back into the same string a caller would
     * have gotten from a real `name` column.
     */
    protected function name(): Attribute
    {
        return Attribute::make(
            get: fn () => trim("{$this->first_name} {$this->last_name}"),
        );
    }

    /**
     * Splits a single "name" input (as Jetstream's registration/profile
     * forms still submit) into first/last name at the first space, since
     * this project has no separate first/last name fields in those forms
     * to read from directly. A name with no space becomes the first name
     * with an empty last name, rather than failing - Jetstream's own
     * validation only requires `name` to be a non-empty string, not that
     * it contain a space.
     *
     * @return array{first_name: string, last_name: string}
     */
    public static function splitName(string $name): array
    {
        [$firstName, $lastName] = array_pad(explode(' ', trim($name), 2), 2, '');

        return ['first_name' => $firstName, 'last_name' => $lastName];
    }

    /**
     * The roles assigned to this user - the only point of contact between
     * Jetstream's authentication and this project's own authorization layer.
     *
     * @return BelongsToMany<Role, $this>
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_user');
    }

    /**
     * Whether this user holds a role granting the given resource/action
     * permission. Resolved fresh from the database on every call, since
     * this project's traffic and role-change frequency don't justify
     * caching permissions across requests.
     */
    public function hasPermission(string $resource, string $action): bool
    {
        return $this->roles()
            ->whereHas('permissions', function ($query) use ($resource, $action) {
                $query->where('resource', $resource)->where('action', $action);
            })
            ->exists();
    }
}
