<?php

declare(strict_types=1);

namespace Anvyr\Loom\Tests\Unit\Database;

use Anvyr\Loom\Tests\Support\Doubles\Models\Post;
use Anvyr\Loom\Tests\Support\Doubles\Models\Profile;
use Anvyr\Loom\Tests\Support\Doubles\Models\User;
use Anvyr\Loom\Tests\Support\ModelTestCase;

final class ModelBuilderTest extends ModelTestCase
{
    public function test_with_eager_loads_has_many(): void
    {
        $user = User::create(['name' => 'Eager', 'email' => 'eager@test.com']);
        Post::create(['user_id' => $user->getKey(), 'title' => 'Post 1']);
        Post::create(['user_id' => $user->getKey(), 'title' => 'Post 2']);

        $users = User::with('posts')->get();
        $loadedUser = $users->first();

        $this->assertTrue($loadedUser->relationLoaded('posts'));
        $this->assertCount(2, $loadedUser->posts);
        $this->assertInstanceOf(Post::class, $loadedUser->posts->first());
    }

    public function test_with_eager_loads_has_one(): void
    {
        $user = User::create(['name' => 'Profile', 'email' => 'profile@test.com']);
        Profile::create(['user_id' => $user->getKey(), 'bio' => 'Hello']);

        $users = User::with('profile')->get();
        $loadedUser = $users->first();

        $this->assertTrue($loadedUser->relationLoaded('profile'));
        $this->assertNotNull($loadedUser->profile);
        $this->assertSame('Hello', $loadedUser->profile->bio);
    }

    public function test_with_eager_loads_belongs_to(): void
    {
        $user = User::create(['name' => 'Author', 'email' => 'author@test.com']);
        $post = Post::create(['user_id' => $user->getKey(), 'title' => 'My Post']);

        $posts = Post::with('author')->get();
        $loadedPost = $posts->first();

        $this->assertTrue($loadedPost->relationLoaded('author'));
        $this->assertSame('Author', $loadedPost->author->name);
    }

    public function test_lazy_loading(): void
    {
        $user = User::create(['name' => 'Lazy', 'email' => 'lazy@test.com']);
        Post::create(['user_id' => $user->getKey(), 'title' => 'Lazy Post']);

        $fetched = User::find($user->getKey());
        $posts = $fetched->posts;

        $this->assertCount(1, $posts);
        $this->assertSame('Lazy Post', $posts->first()->title);
    }

    public function test_where_in(): void
    {
        User::create(['name' => 'In1', 'email' => 'in1@test.com']);
        User::create(['name' => 'In2', 'email' => 'in2@test.com']);
        User::create(['name' => 'Out', 'email' => 'out@test.com']);

        $results = User::query()->whereIn('email', ['in1@test.com', 'in2@test.com'])->get();
        $this->assertCount(2, $results);
    }

    public function test_order_by(): void
    {
        User::create(['name' => 'Zoe', 'email' => 'zoe@test.com']);
        User::create(['name' => 'Alice', 'email' => 'alice@test.com']);

        $users = User::query()->orderBy('name', 'ASC')->get();
        $this->assertSame('Alice', $users->first()->name);
    }

    public function test_limit(): void
    {
        User::create(['name' => 'L1', 'email' => 'l1@test.com']);
        User::create(['name' => 'L2', 'email' => 'l2@test.com']);
        User::create(['name' => 'L3', 'email' => 'l3@test.com']);

        $users = User::query()->limit(2)->get();
        $this->assertCount(2, $users);
    }

    public function test_offset(): void
    {
        User::create(['name' => 'O1', 'email' => 'o1@test.com']);
        User::create(['name' => 'O2', 'email' => 'o2@test.com']);
        User::create(['name' => 'O3', 'email' => 'o3@test.com']);

        $users = User::query()->orderBy('id', 'ASC')->offset(1)->limit(2)->get();
        $this->assertCount(2, $users);
    }

    public function test_find_returns_model(): void
    {
        $user = User::create(['name' => 'Find', 'email' => 'find@test.com']);
        $found = User::query()->find($user->getKey());

        $this->assertInstanceOf(User::class, $found);
        $this->assertSame('Find', $found->name);
    }

    public function test_chained_where(): void
    {
        User::create(['name' => 'Chain', 'email' => 'chain@test.com', 'is_active' => true]);
        User::create(['name' => 'Chain2', 'email' => 'chain2@test.com', 'is_active' => false]);

        $result = User::query()
            ->where('is_active', '=', true)
            ->where('name', '=', 'Chain')
            ->get();

        $this->assertCount(1, $result);
    }

    public function test_model_builder_get_returns_collection_of_models(): void
    {
        User::create(['name' => 'B1', 'email' => 'b1@test.com']);
        User::create(['name' => 'B2', 'email' => 'b2@test.com']);

        $collection = User::query()->get();

        foreach ($collection as $item) {
            $this->assertInstanceOf(User::class, $item);
        }
    }

    public function test_multiple_with(): void
    {
        $user = User::create(['name' => 'Multi', 'email' => 'multi@test.com']);
        Post::create(['user_id' => $user->getKey(), 'title' => 'MP']);
        Profile::create(['user_id' => $user->getKey(), 'bio' => 'MP Bio']);

        $result = User::with(['posts', 'profile'])->first();

        $this->assertTrue($result->relationLoaded('posts'));
        $this->assertTrue($result->relationLoaded('profile'));
    }
}
