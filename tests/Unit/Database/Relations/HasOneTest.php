<?php

declare(strict_types=1);

namespace Anvyr\Loom\Tests\Unit\Database\Relations;

use Anvyr\Loom\Tests\Support\Doubles\Models\Profile;
use Anvyr\Loom\Tests\Support\Doubles\Models\User;
use Anvyr\Loom\Tests\Support\ModelTestCase;

final class HasOneTest extends ModelTestCase
{
    public function test_has_one_returns_model(): void
    {
        $user = User::create(['name' => 'HasOne', 'email' => 'hasone@test.com']);
        Profile::create(['user_id' => $user->getKey(), 'bio' => 'My bio']);

        $profile = $user->profile;

        $this->assertInstanceOf(Profile::class, $profile);
        $this->assertSame('My bio', $profile->bio);
    }

    public function test_has_one_returns_null_when_none(): void
    {
        $user = User::create(['name' => 'NoProfile', 'email' => 'noprof@test.com']);
        $profile = $user->profile;

        $this->assertNull($profile);
    }

    public function test_has_one_eager_loading(): void
    {
        $user = User::create(['name' => 'EagerOne', 'email' => 'eagerone@test.com']);
        Profile::create(['user_id' => $user->getKey(), 'bio' => 'Eager bio']);

        $loaded = User::with('profile')->first();

        $this->assertTrue($loaded->relationLoaded('profile'));
        $this->assertSame('Eager bio', $loaded->profile->bio);
    }
}
