<?php

declare(strict_types=1);

namespace Anvyr\Loom\Tests\Support\Doubles\Models;

use Anvyr\Loom\Database\Model;
use Anvyr\Loom\Database\Relations\BelongsTo;

class Profile extends Model
{
    protected array $fillable = ['user_id', 'bio', 'website'];
    protected array $guarded = [];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
