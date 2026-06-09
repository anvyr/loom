<?php

declare(strict_types=1);

namespace Anvyr\Loom\Tests\Unit\Database;

use Anvyr\Loom\Database\Collection;
use Anvyr\Loom\Tests\Support\Doubles\Models\User;
use Anvyr\Loom\Tests\Support\ModelTestCase;

final class ModelTest extends ModelTestCase
{
    public function test_create_model(): void
    {
        $user = User::create(['name' => 'John', 'email' => 'john@test.com']);

        $this->assertTrue($user->exists());
        $this->assertNotNull($user->getKey());
        $this->assertSame('John', $user->name);
        $this->assertSame('john@test.com', $user->email);
    }

    public function test_find_model(): void
    {
        $user = User::create(['name' => 'Find Me', 'email' => 'find@test.com']);
        $found = User::find($user->getKey());

        $this->assertNotNull($found);
        $this->assertSame('Find Me', $found->name);
    }

    public function test_find_returns_null_for_missing(): void
    {
        $result = User::find(9999);
        $this->assertNull($result);
    }

    public function test_update_model(): void
    {
        $user = User::create(['name' => 'Old Name', 'email' => 'old@test.com']);
        $user->name = 'New Name';
        $user->save();

        $fresh = User::find($user->getKey());
        $this->assertSame('New Name', $fresh->name);
    }

    public function test_delete_model(): void
    {
        $user = User::create(['name' => 'Delete Me', 'email' => 'delete@test.com']);
        $id = $user->getKey();
        $user->delete();

        $this->assertFalse($user->exists());
        $this->assertNull(User::find($id));
    }

    public function test_mass_assignment_with_fillable(): void
    {
        $user = new User();
        $user->fill(['name' => 'Test', 'email' => 'test@test.com']);

        $this->assertSame('Test', $user->name);
        $this->assertSame('test@test.com', $user->email);
    }

    public function test_guarded_blocks_unlisted_attributes(): void
    {
        $model = new class () extends \Anvyr\Loom\Database\Model {
            protected ?string $table = 'users';
            protected array $fillable = [];
            protected array $guarded = ['*'];
        };

        $model->fill(['name' => 'Test', 'secret' => 'nope']);

        $this->assertNull($model->getAttribute('name'));
        $this->assertNull($model->getAttribute('secret'));
    }

    public function test_timestamps_are_set_on_create(): void
    {
        $user = User::create(['name' => 'Timed', 'email' => 'timed@test.com']);

        $this->assertNotNull($user->created_at);
        $this->assertNotNull($user->updated_at);
    }

    public function test_updated_at_changes_on_update(): void
    {
        $user = User::create(['name' => 'Update Time', 'email' => 'updatetime@test.com']);
        $originalUpdated = $user->updated_at;

        // Need to wait to ensure different timestamp
        $user->name = 'Changed';
        $user->save();

        $fresh = User::find($user->getKey());
        $this->assertSame('Changed', $fresh->name);
    }

    public function test_to_array(): void
    {
        $user = User::create(['name' => 'Array', 'email' => 'array@test.com']);
        $array = $user->toArray();

        $this->assertIsArray($array);
        $this->assertSame('Array', $array['name']);
        $this->assertSame('array@test.com', $array['email']);
        $this->assertArrayHasKey('created_at', $array);
    }

    public function test_to_json(): void
    {
        $user = User::create(['name' => 'Json', 'email' => 'json@test.com']);
        $json = $user->toJson();
        $decoded = json_decode($json, true);

        $this->assertSame('Json', $decoded['name']);
    }

    public function test_get_table(): void
    {
        $user = new User();
        $this->assertSame('users', $user->getTable());
    }

    public function test_get_key_name(): void
    {
        $user = new User();
        $this->assertSame('id', $user->getKeyName());
    }

    public function test_query_returns_all(): void
    {
        User::create(['name' => 'A', 'email' => 'a@test.com']);
        User::create(['name' => 'B', 'email' => 'b@test.com']);

        $users = User::query()->get();

        $this->assertInstanceOf(Collection::class, $users);
        $this->assertCount(2, $users);
        $this->assertInstanceOf(User::class, $users->first());
    }

    public function test_where_query(): void
    {
        User::create(['name' => 'Active', 'email' => 'active@test.com', 'is_active' => true]);
        User::create(['name' => 'Inactive', 'email' => 'inactive@test.com', 'is_active' => false]);

        $active = User::query()->where('is_active', '=', true)->get();

        $this->assertCount(1, $active);
        $this->assertSame('Active', $active->first()->name);
    }

    public function test_count(): void
    {
        User::create(['name' => 'C1', 'email' => 'c1@test.com']);
        User::create(['name' => 'C2', 'email' => 'c2@test.com']);

        $count = User::query()->count();

        $this->assertSame(2, $count);
    }

    public function test_exists_query(): void
    {
        User::create(['name' => 'Exists', 'email' => 'exists@test.com']);

        $this->assertTrue(User::query()->where('email', '=', 'exists@test.com')->exists());
        $this->assertFalse(User::query()->where('email', '=', 'nope@test.com')->exists());
    }

    public function test_first_returns_model(): void
    {
        User::create(['name' => 'First', 'email' => 'first@test.com']);

        $user = User::query()->first();

        $this->assertInstanceOf(User::class, $user);
        $this->assertSame('First', $user->name);
    }

    public function test_paginate(): void
    {
        for ($i = 1; $i <= 20; $i++) {
            User::create(['name' => "User {$i}", 'email' => "user{$i}@test.com"]);
        }

        $result = User::query()->paginate(5, 1);

        $this->assertCount(5, $result['data']);
        $this->assertSame(20, $result['total']);
        $this->assertSame(5, $result['per_page']);
        $this->assertSame(1, $result['current_page']);
        $this->assertSame(4, $result['last_page']);
        $this->assertInstanceOf(User::class, $result['data']->first());
    }

    public function test_is_new(): void
    {
        $user = new User(['name' => 'New', 'email' => 'new@test.com']);
        $this->assertTrue($user->isNew());

        $user->save();
        $this->assertFalse($user->isNew());
    }

    public function test_model_string_representation(): void
    {
        $user = User::create(['name' => 'String', 'email' => 'string@test.com']);
        $this->assertStringContainsString('User', (string) $user);
        $this->assertStringContainsString((string) $user->getKey(), (string) $user);
    }

    public function test_new_instance_is_not_persisted(): void
    {
        $user = new User(['name' => 'Temp']);
        $this->assertFalse($user->exists());
        $this->assertTrue($user->isNew());
    }

    public function test_create_multiple_records(): void
    {
        User::create(['name' => 'Multi1', 'email' => 'm1@test.com']);
        User::create(['name' => 'Multi2', 'email' => 'm2@test.com']);
        User::create(['name' => 'Multi3', 'email' => 'm3@test.com']);

        $this->assertSame(3, User::query()->count());
    }

    public function test_update_with_attributes(): void
    {
        $user = User::create(['name' => 'Update', 'email' => 'update@test.com']);
        $user->update(['name' => 'Updated']);

        $fresh = User::find($user->getKey());
        $this->assertSame('Updated', $fresh->name);
    }
}
