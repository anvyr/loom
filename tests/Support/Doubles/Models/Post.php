<?php

declare(strict_types=1);

namespace Anvyr\Loom\Tests\Support\Doubles\Models;

use Anvyr\Loom\Database\Concerns\SoftDeletes;
use Anvyr\Loom\Database\Model;
use Anvyr\Loom\Database\Relations\BelongsTo;

class Post extends Model
{
    use SoftDeletes;

    protected array $fillable = ['user_id', 'title', 'body', 'is_published'];
    protected array $guarded = [];

    protected array $casts = [
        'is_published' => 'bool',
    ];

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
