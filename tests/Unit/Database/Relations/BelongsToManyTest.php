<?php

declare(strict_types=1);

namespace Anvyr\Loom\Tests\Unit\Database\Relations;

use Anvyr\Loom\Tests\Support\Doubles\Models\Role;
use Anvyr\Loom\Tests\Support\Doubles\Models\User;
use Anvyr\Loom\Tests\Support\ModelTestCase;

final class BelongsToManyTest extends ModelTestCase
{
    public function test_belongsToMany_returns_collection(): void
    {
        $user = User::create(['name' => 'B2M', 'email' => 'b2m@test.com']);
        $role = Role::create(['name' => 'admin']);
        $user->roles()->attach($role->getKey());

        $roles = $user->roles;

        $this->assertCount(1, $roles);
        $this->assertSame('admin', $roles->first()->name);
    }

    public function test_attach(): void
    {
        $user = User::create(['name' => 'Attach', 'email' => 'attach@test.com']);
        $role = Role::create(['name' => 'editor']);

        $user->roles()->attach($role->getKey());

        $this->assertTrue($user->roles()->isAttached($role->getKey()));
    }

    public function test_detach(): void
    {
        $user = User::create(['name' => 'Detach', 'email' => 'detach@test.com']);
        $role = Role::create(['name' => 'viewer']);

        $user->roles()->attach($role->getKey());
        $this->assertTrue($user->roles()->isAttached($role->getKey()));

        $user->roles()->detach($role->getKey());
        $this->assertFalse($user->roles()->isAttached($role->getKey()));
    }

    public function test_sync(): void
    {
        $user = User::create(['name' => 'Sync', 'email' => 'sync@test.com']);
        $r1 = Role::create(['name' => 'r1']);
        $r2 = Role::create(['name' => 'r2']);
        $r3 = Role::create(['name' => 'r3']);

        $user->roles()->attach([$r1->getKey(), $r2->getKey()]);
        $user->roles()->sync([$r2->getKey(), $r3->getKey()]);

        $roles = $user->roles;
        $this->assertCount(2, $roles);
        $this->assertFalse($user->roles()->isAttached($r1->getKey()));
        $this->assertTrue($user->roles()->isAttached($r2->getKey()));
        $this->assertTrue($user->roles()->isAttached($r3->getKey()));
    }

    public function test_toggle(): void
    {
        $user = User::create(['name' => 'Toggle', 'email' => 'toggle@test.com']);
        $r1 = Role::create(['name' => 't1']);
        $r2 = Role::create(['name' => 't2']);

        $user->roles()->attach($r1->getKey());

        $user->roles()->toggle([$r1->getKey(), $r2->getKey()]);

        $this->assertFalse($user->roles()->isAttached($r1->getKey()));
        $this->assertTrue($user->roles()->isAttached($r2->getKey()));
    }

    public function test_eager_loading_belongsToMany(): void
    {
        $user = User::create(['name' => 'EagerB2M', 'email' => 'eagerb2m@test.com']);
        $role = Role::create(['name' => 'superadmin']);
        $user->roles()->attach($role->getKey());

        $loaded = User::with('roles')->first();

        $this->assertTrue($loaded->relationLoaded('roles'));
        $this->assertCount(1, $loaded->roles);
        $this->assertSame('superadmin', $loaded->roles->first()->name);
    }

    public function test_multiple_users_with_roles(): void
    {
        $u1 = User::create(['name' => 'U1', 'email' => 'u1@test.com']);
        $u2 = User::create(['name' => 'U2', 'email' => 'u2@test.com']);
        $admin = Role::create(['name' => 'admin']);
        $editor = Role::create(['name' => 'editor']);

        $u1->roles()->attach([$admin->getKey(), $editor->getKey()]);
        $u2->roles()->attach([$editor->getKey()]);

        $users = User::with('roles')->orderBy('id')->get();

        $this->assertCount(2, $users->first()->roles);
        $this->assertCount(1, $users->get(1)->roles);
    }
}
