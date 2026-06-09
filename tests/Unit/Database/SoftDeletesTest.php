<?php

declare(strict_types=1);

namespace Anvyr\Loom\Tests\Unit\Database;

use Anvyr\Loom\Tests\Support\Doubles\Models\Post;
use Anvyr\Loom\Tests\Support\Doubles\Models\User;
use Anvyr\Loom\Tests\Support\ModelTestCase;

final class SoftDeletesTest extends ModelTestCase
{
    protected function seedData(): void
    {
        $user = User::create(['name' => 'SD User', 'email' => 'sd@test.com']);
        Post::create(['user_id' => $user->getKey(), 'title' => 'Active Post']);
        Post::create(['user_id' => $user->getKey(), 'title' => 'Deleted Post']);
    }

    public function test_soft_delete_sets_deleted_at(): void
    {
        $post = Post::query()->where('title', '=', 'Active Post')->first();
        $post->softDelete();

        $this->assertNotNull($post->deleted_at);
        $this->assertTrue($post->trashed());
    }

    public function test_soft_deleted_excluded_by_default(): void
    {
        $post = Post::query()->where('title', '=', 'Deleted Post')->first();
        $post->softDelete();

        $posts = Post::query()->get();
        $this->assertCount(1, $posts);
        $this->assertSame('Active Post', $posts->first()->title);
    }

    public function test_with_trashed_includes_soft_deleted(): void
    {
        $post = Post::query()->where('title', '=', 'Deleted Post')->first();
        $post->softDelete();

        $all = Post::query()->withTrashed()->get();
        $this->assertCount(2, $all);
    }

    public function test_only_trashed(): void
    {
        $post = Post::query()->where('title', '=', 'Deleted Post')->first();
        $post->softDelete();

        $trashed = Post::query()->onlyTrashed()->get();
        $this->assertCount(1, $trashed);
        $this->assertSame('Deleted Post', $trashed->first()->title);
    }

    public function test_restore(): void
    {
        $post = Post::query()->where('title', '=', 'Deleted Post')->first();
        $post->softDelete();
        $this->assertTrue($post->trashed());

        $post->restore();
        $this->assertFalse($post->trashed());

        $posts = Post::query()->get();
        $this->assertCount(2, $posts);
    }

    public function test_find_excludes_soft_deleted(): void
    {
        $post = Post::query()->where('title', '=', 'Deleted Post')->first();
        $id = $post->getKey();
        $post->softDelete();

        $found = Post::find($id);
        $this->assertNull($found);
    }

    public function test_force_delete_removes_permanently(): void
    {
        $post = Post::query()->where('title', '=', 'Active Post')->first();
        $post->forceDelete();

        $posts = Post::query()->withTrashed()->get();
        $this->assertCount(1, $posts);
    }
}
