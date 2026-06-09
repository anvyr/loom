<?php

declare(strict_types=1);

namespace Anvyr\Loom\Tests\Unit\Services;

use Anvyr\Loom\Services\SessionManager;
use Anvyr\Loom\Tests\Support\TestCase;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

#[RunTestsInSeparateProcesses]
final class SessionManagerTest extends TestCase
{
    private SessionManager $session;

    protected function setUp(): void
    {
        parent::setUp();

        // Mock session start
        if (session_status() !== PHP_SESSION_ACTIVE) {
            // We can't really start a session in CLI easily without headers sent issues,
            // but we can manipulate $_SESSION directly which SessionManager uses.
            // However, SessionManager checks session_status() in constructor.
            // We might need to bypass that check or assume it's false in CLI.
            // The class sets $started = session_status() === PHP_SESSION_ACTIVE.
            // But the methods don't check $started, they just use $_SESSION.
        }

        $_SESSION = [];
        $this->session = new SessionManager();
    }

    public function test_can_set_and_get_values(): void
    {
        $this->session->set('foo', 'bar');
        $this->assertSame('bar', $this->session->get('foo'));
        $this->assertSame('bar', $_SESSION['foo']);
    }

    public function test_can_set_and_get_nested_values(): void
    {
        $this->session->set('user.profile.name', 'John');

        $this->assertSame('John', $this->session->get('user.profile.name'));
        $this->assertIsArray($_SESSION['user']);
        $this->assertSame('John', $_SESSION['user']['profile']['name']);
    }

    public function test_returns_default_if_key_missing(): void
    {
        $this->assertSame('default', $this->session->get('missing', 'default'));
        $this->assertSame('default', $this->session->get('nested.missing', 'default'));
    }

    public function test_can_check_existence(): void
    {
        $this->session->set('exists', true);

        $this->assertTrue($this->session->has('exists'));
        $this->assertFalse($this->session->has('missing'));
    }

    public function test_can_delete_values(): void
    {
        $this->session->set('foo', 'bar');
        $this->session->delete('foo');

        $this->assertFalse($this->session->has('foo'));
        $this->assertArrayNotHasKey('foo', $_SESSION);
    }

    public function test_can_delete_nested_values(): void
    {
        $this->session->set('a.b.c', 'value');
        $this->session->delete('a.b.c');

        $this->assertFalse($this->session->has('a.b.c'));
        $this->assertTrue($this->session->has('a.b')); // Parent should still exist
    }

    public function test_flash_data_lifecycle(): void
    {
        // 1. Set flash
        $this->session->flash('message', 'Hello');
        $this->assertTrue($this->session->has('_flash.new.message'));

        // 2. Age flash (move new to old)
        $this->session->ageFlashData();
        $this->assertFalse($this->session->has('_flash.new.message'));
        $this->assertTrue($this->session->has('_flash.old.message'));
        $this->assertSame('Hello', $this->session->getFlash('message'));

        // 3. Age again (delete old)
        $this->session->ageFlashData();
        $this->assertFalse($this->session->has('_flash.old.message'));
        $this->assertNull($this->session->getFlash('message'));
    }
}
