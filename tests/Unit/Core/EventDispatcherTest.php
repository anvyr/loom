<?php

declare(strict_types=1);

namespace Anvyr\Loom\Tests\Unit\Core;

use Anvyr\Loom\Core\EventDispatcher;
use Anvyr\Loom\Tests\Support\TestCase;

final class EventDispatcherTest extends TestCase
{
    private EventDispatcher $events;

    protected function setUp(): void
    {
        parent::setUp();
        $this->events = new EventDispatcher();
    }

    public function test_listen_registers_callback(): void
    {
        $called = false;
        $this->events->listen('test.event', function () use (&$called) {
            $called = true;
        });

        $this->events->dispatch('test.event');

        $this->assertTrue($called);
    }

    public function test_dispatch_passes_payload_to_listener(): void
    {
        $received = null;
        $this->events->listen('user.created', function ($payload) use (&$received) {
            $received = $payload;
        });

        $this->events->dispatch('user.created', ['id' => 123, 'name' => 'John']);

        $this->assertSame(['id' => 123, 'name' => 'John'], $received);
    }

    public function test_multiple_listeners_execute_in_order(): void
    {
        $order = [];

        $this->events->listen('multi', function () use (&$order) {
            $order[] = 'first';
        });
        $this->events->listen('multi', function () use (&$order) {
            $order[] = 'second';
        });
        $this->events->listen('multi', function () use (&$order) {
            $order[] = 'third';
        });

        $this->events->dispatch('multi');

        $this->assertSame(['first', 'second', 'third'], $order);
    }

    public function test_dispatch_without_listeners_does_not_error(): void
    {
        $this->events->dispatch('no.listeners', 'data');

        $this->assertSame([], $this->events->getEvents());
    }

    public function test_has_listeners_returns_true_when_registered(): void
    {
        $this->events->listen('has.test', fn () => null);

        $this->assertTrue($this->events->hasListeners('has.test'));
    }

    public function test_has_listeners_returns_false_when_empty(): void
    {
        $this->assertFalse($this->events->hasListeners('no.listeners'));
    }

    public function test_has_listeners_returns_false_after_forget(): void
    {
        $this->events->listen('forget.test', fn () => null);
        $this->assertTrue($this->events->hasListeners('forget.test'));

        $this->events->forget('forget.test');

        $this->assertFalse($this->events->hasListeners('forget.test'));
    }

    public function test_forget_removes_all_listeners_for_event(): void
    {
        $called = false;
        $this->events->listen('forget.me', function () use (&$called) {
            $called = true;
        });

        $this->events->forget('forget.me');
        $this->events->dispatch('forget.me');

        $this->assertFalse($called);
    }

    public function test_forget_nonexistent_event_does_not_error(): void
    {
        $this->events->forget('does.not.exist');

        $this->assertSame([], $this->events->getEvents());
    }

    public function test_get_events_returns_registered_event_names(): void
    {
        $this->events->listen('event.one', fn () => null);
        $this->events->listen('event.two', fn () => null);
        $this->events->listen('event.three', fn () => null);

        $events = $this->events->getEvents();

        $this->assertContains('event.one', $events);
        $this->assertContains('event.two', $events);
        $this->assertContains('event.three', $events);
    }

    public function test_get_events_returns_empty_array_when_none(): void
    {
        $events = $this->events->getEvents();

        $this->assertSame([], $events);
    }

    public function test_listener_can_modify_object_payload(): void
    {
        $this->events->listen('modify.object', function ($obj) {
            $obj->modified = true;
        });

        $payload = new \stdClass();
        $payload->modified = false;

        $this->events->dispatch('modify.object', $payload);

        $this->assertTrue($payload->modified);
    }

    public function test_listener_cannot_modify_array_payload(): void
    {
        $this->events->listen('modify.array', function ($arr) {
            $arr['modified'] = true;
        });

        $payload = ['modified' => false];

        $this->events->dispatch('modify.array', $payload);

        $this->assertFalse($payload['modified']);
    }

    public function test_null_payload_is_handled(): void
    {
        $received = 'not-null';
        $this->events->listen('null.payload', function ($payload) use (&$received) {
            $received = $payload;
        });

        $this->events->dispatch('null.payload', null);

        $this->assertNull($received);
    }
}
