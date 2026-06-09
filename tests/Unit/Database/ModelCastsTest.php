<?php

declare(strict_types=1);

namespace Anvyr\Loom\Tests\Unit\Database;

use Anvyr\Loom\Database\Model;
use Anvyr\Loom\Tests\Support\Doubles\Models\User;
use Anvyr\Loom\Tests\Support\ModelTestCase;

final class ModelCastsTest extends ModelTestCase
{
    public function test_bool_cast(): void
    {
        $user = User::create(['name' => 'Bool', 'email' => 'bool@test.com', 'is_active' => true]);
        $this->assertIsBool($user->is_active);
        $this->assertTrue($user->is_active);
    }

    public function test_bool_cast_false(): void
    {
        $user = User::create(['name' => 'BoolF', 'email' => 'boolf@test.com', 'is_active' => false]);
        $this->assertIsBool($user->is_active);
        $this->assertFalse($user->is_active);
    }

    public function test_json_cast(): void
    {
        $settings = ['theme' => 'dark', 'lang' => 'en'];
        $user = User::create(['name' => 'Json', 'email' => 'json@test.com', 'settings' => $settings]);

        $this->assertIsArray($user->settings);
        $this->assertSame('dark', $user->settings['theme']);
    }

    public function test_float_cast(): void
    {
        $user = User::create(['name' => 'Float', 'email' => 'float@test.com', 'score' => 42.5]);
        $this->assertIsFloat($user->score);
        $this->assertSame(42.5, $user->score);
    }

    public function test_accessor(): void
    {
        $user = new class (['name' => 'John Doe', 'email' => 'test@test.com']) extends Model {
            protected ?string $table = 'users';
            protected array $guarded = [];

            public function getDisplayNameAttribute(?string $value): string
            {
                return strtoupper($this->attributes['name'] ?? '');
            }
        };

        // displayName isn't a stored attribute, but the accessor should still work
        $this->assertSame('JOHN DOE', $user->displayName);
    }

    public function test_mutator(): void
    {
        $user = new class () extends Model {
            protected ?string $table = 'users';
            protected array $guarded = [];

            public function setNameAttribute(string $value): void
            {
                $this->attributes['name'] = strtolower($value);
            }
        };

        $user->name = 'JOHN DOE';
        $this->assertSame('john doe', $user->name);
    }

    public function test_dirty_tracking(): void
    {
        $user = User::create(['name' => 'Clean', 'email' => 'clean@test.com']);
        $this->assertFalse($user->isDirty());

        $user->name = 'Dirty';
        $this->assertTrue($user->isDirty());
        $this->assertTrue($user->isDirty('name'));
        $this->assertFalse($user->isDirty('email'));
    }

    public function test_get_dirty(): void
    {
        $user = User::create(['name' => 'Dirty', 'email' => 'dirty@test.com']);
        $user->name = 'Changed';

        $dirty = $user->getDirty();
        $this->assertArrayHasKey('name', $dirty);
        $this->assertSame('Changed', $dirty['name']);
        $this->assertArrayNotHasKey('email', $dirty);
    }

    public function test_get_original(): void
    {
        $user = User::create(['name' => 'Orig', 'email' => 'orig@test.com']);
        $user->name = 'Changed';

        $this->assertSame('Orig', $user->getOriginal('name'));
        $this->assertSame('Changed', $user->getAttribute('name'));
    }

    public function test_sync_original(): void
    {
        $user = User::create(['name' => 'Sync', 'email' => 'sync@test.com']);
        $user->name = 'Changed';
        $this->assertTrue($user->isDirty());

        $user->syncOriginal();
        $this->assertFalse($user->isDirty());
    }

    public function test_is_clean(): void
    {
        $user = User::create(['name' => 'Clean', 'email' => 'clean@test.com']);
        $this->assertTrue($user->isClean());
        $this->assertTrue($user->isClean('name'));
    }

    public function test_set_raw_attributes(): void
    {
        $user = new User();
        $user->setRawAttributes(['id' => 1, 'name' => 'Raw', 'email' => 'raw@test.com']);

        $this->assertSame('Raw', $user->name);
        $this->assertSame(1, $user->getKey());
    }

    public function test_attributes_to_array(): void
    {
        $user = User::create(['name' => 'Array', 'email' => 'array@test.com', 'is_active' => true]);
        $attrs = $user->attributesToArray();

        $this->assertIsBool($attrs['is_active']);
        $this->assertTrue($attrs['is_active']);
    }
}
