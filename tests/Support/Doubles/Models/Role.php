<?php

declare(strict_types=1);

namespace Anvyr\Loom\Tests\Support\Doubles\Models;

use Anvyr\Loom\Database\Model;
use Anvyr\Loom\Database\Relations\BelongsToMany;

class Role extends Model
{
    protected array $fillable = ['name'];
    protected array $guarded = [];

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }
}
