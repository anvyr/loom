<?php

declare(strict_types=1);

namespace Anvyr\Loom\Tests\Support\Doubles\Models;

use Anvyr\Loom\Database\Model;
use Anvyr\Loom\Database\Relations\BelongsToMany;
use Anvyr\Loom\Database\Relations\HasMany;
use Anvyr\Loom\Database\Relations\HasOne;

class User extends Model
{
    protected array $fillable = ['name', 'email', 'is_active', 'settings', 'score'];
    protected array $guarded = [];

    protected array $casts = [
        'is_active' => 'bool',
        'settings' => 'json',
        'score' => 'float',
    ];

    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }

    public function profile(): HasOne
    {
        return $this->hasOne(Profile::class);
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class);
    }

    public function publishedPosts(): HasMany
    {
        return $this->hasMany(Post::class)->where('is_published', '=', 1);
    }
}
