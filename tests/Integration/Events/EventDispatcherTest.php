<?php

declare(strict_types=1);

namespace Anvyr\Loom\Tests\Integration\Events;

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

    public function test_listeners_execute_in_registration_order(): void
    {
        $executionOrder = [];

        $this->events->listen('test.event', function ($payload) use (&$executionOrder) {
            $executionOrder[] = 'first';
        });

        $this->events->listen('test.event', function ($payload) use (&$executionOrder) {
            $executionOrder[] = 'second';
        });

        $this->events->listen('test.event', function ($payload) use (&$executionOrder) {
            $executionOrder[] = 'third';
        });

        $this->events->dispatch('test.event', null);

        $this->assertSame(['first', 'second', 'third'], $executionOrder);
    }

    public function test_dispatch_does_not_return_payload(): void
    {
        $this->events->listen('transform.number', function ($value) {
            return $value * 2;
        });

        $result = $this->events->dispatch('transform.number', 5);

        $this->assertNull($result);
    }

    public function test_listeners_cannot_mutate_array_payload(): void
    {
        $this->events->listen('page.loading', function ($page) {
            $page['cached'] = true;
            return $page;
        });

        $payload = ['slug' => 'test'];
        $this->events->dispatch('page.loading', $payload);

        $this->assertArrayNotHasKey('cached', $payload);
    }

    public function test_listeners_can_mutate_object_payload_by_reference(): void
    {
        $this->events->listen('user.creating', function ($user) {
            $user->validated = true;
        });

        $user = new \stdClass();
        $user->name = 'John';

        $this->events->dispatch('user.creating', $user);

        $this->assertTrue($user->validated);
    }
}
