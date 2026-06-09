<?php

declare(strict_types=1);

namespace Anvyr\Loom\Tests\Unit\Database\Relations;

use Anvyr\Loom\Database\Collection;
use Anvyr\Loom\Tests\Support\Doubles\Models\Post;
use Anvyr\Loom\Tests\Support\Doubles\Models\User;
use Anvyr\Loom\Tests\Support\ModelTestCase;

final class HasManyTest extends ModelTestCase
{
    public function test_has_many_returns_collection(): void
    {
        $user = User::create(['name' => 'HasMany', 'email' => 'hasmany@test.com']);
        Post::create(['user_id' => $user->getKey(), 'title' => 'Post 1']);
        Post::create(['user_id' => $user->getKey(), 'title' => 'Post 2']);

        $posts = $user->posts;

        $this->assertInstanceOf(Collection::class, $posts);
        $this->assertCount(2, $posts);
    }

    public function test_has_many_returns_empty_when_none(): void
    {
        $user = User::create(['name' => 'NoPosts', 'email' => 'noposts@test.com']);
        $posts = $user->posts;

        $this->assertInstanceOf(Collection::class, $posts);
        $this->assertCount(0, $posts);
    }

    public function test_has_many_eager_loading(): void
    {
        $user = User::create(['name' => 'EagerMany', 'email' => 'eagermany@test.com']);
        Post::create(['user_id' => $user->getKey(), 'title' => 'EP 1']);
        Post::create(['user_id' => $user->getKey(), 'title' => 'EP 2']);

        $loaded = User::with('posts')->first();

        $this->assertTrue($loaded->relationLoaded('posts'));
        $this->assertCount(2, $loaded->posts);
    }

    public function test_has_many_only_returns_related(): void
    {
        $user1 = User::create(['name' => 'U1', 'email' => 'u1@test.com']);
        $user2 = User::create(['name' => 'U2', 'email' => 'u2@test.com']);
        Post::create(['user_id' => $user1->getKey(), 'title' => 'U1 Post']);
        Post::create(['user_id' => $user2->getKey(), 'title' => 'U2 Post']);

        $posts = $user1->posts;

        $this->assertCount(1, $posts);
        $this->assertSame('U1 Post', $posts->first()->title);
    }
}
