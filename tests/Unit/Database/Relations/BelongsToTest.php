<?php

declare(strict_types=1);

namespace Anvyr\Loom\Tests\Unit\Database\Relations;

use Anvyr\Loom\Tests\Support\Doubles\Models\Post;
use Anvyr\Loom\Tests\Support\Doubles\Models\User;
use Anvyr\Loom\Tests\Support\ModelTestCase;

final class BelongsToTest extends ModelTestCase
{
    public function test_belongs_to_returns_parent(): void
    {
        $user = User::create(['name' => 'Author', 'email' => 'author@test.com']);
        $post = Post::create(['user_id' => $user->getKey(), 'title' => 'My Post']);

        $author = $post->author;

        $this->assertInstanceOf(User::class, $author);
        $this->assertSame('Author', $author->name);
    }

    public function test_belongs_to_eager_loading(): void
    {
        $user = User::create(['name' => 'EagerAuthor', 'email' => 'eagerauth@test.com']);
        Post::create(['user_id' => $user->getKey(), 'title' => 'Eager Post']);

        $posts = Post::with('author')->get();
        $post = $posts->first();

        $this->assertTrue($post->relationLoaded('author'));
        $this->assertSame('EagerAuthor', $post->author->name);
    }

    public function test_belongs_to_multiple_posts_same_author(): void
    {
        $user = User::create(['name' => 'MultiAuthor', 'email' => 'multiauth@test.com']);
        Post::create(['user_id' => $user->getKey(), 'title' => 'Post A']);
        Post::create(['user_id' => $user->getKey(), 'title' => 'Post B']);

        $posts = Post::with('author')->get();

        foreach ($posts as $post) {
            $this->assertSame('MultiAuthor', $post->author->name);
        }
    }
}
