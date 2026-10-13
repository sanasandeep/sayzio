<?php

namespace Tests\Feature;

use App\Modules\User\Controllers\InboxUnifiedController;
use App\Modules\User\Models\InboxThread;
use App\Modules\User\Models\User;
use App\Modules\User\Models\Workspace;
use App\Modules\User\Services\Inbox\InboxThreadSync;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Str;
use Mockery;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class InboxUnifiedPolishTest extends TestCase
{
    use RefreshDatabase;

    private function setupInbox(): array
    {
        $owner = User::create(['name' => 'Owner', 'email' => Str::random(10).'@example.com', 'password' => bcrypt('test'), 'status' => 'active']);
        $ws = Workspace::create(['owner_user_id' => $owner->id, 'name' => 'Team', 'slug' => Str::random(10), 'is_personal' => false]);
        $this->app->instance('current_workspace', $ws);
        $this->actingAs($owner);
        $sync = Mockery::mock(InboxThreadSync::class);
        $sync->shouldReceive('syncWorkspace')->andReturn(0);
        $this->app->instance(InboxThreadSync::class, $sync);

        return [$owner, $ws];
    }

    private function thread($u, $ws, $extra = []): InboxThread
    {
        return InboxThread::create(array_merge(['workspace_id' => $ws->id, 'user_id' => $u->id, 'source_type' => 'email', 'source_id' => random_int(100, 999999), 'channel' => 'email', 'sender_name' => 'A long sender name', 'sender_email' => 'person@example.com', 'subject' => 'Subject', 'preview' => 'Message body', 'category' => 'support', 'status' => 'open', 'last_message_at' => now(), 'sla_due_at' => now()->subDay()], $extra));
    }

    public function test_counts_and_list_exclude_private_threads_for_viewer(): void
    {
        [$u,$ws] = $this->setupInbox();
        $this->thread($u, $ws);
        $this->thread($u, $ws, ['is_private' => true]);
        $viewer = User::create(['name' => 'Viewer', 'email' => Str::random(10).'@example.com', 'password' => bcrypt('test'), 'status' => 'active']);
        $this->actingAs($viewer);
        $data = app(InboxUnifiedController::class)->index(Request::create('/'))->getData();
        $this->assertSame(1, $data['counts']['all']);
        $this->assertSame(1, $data['counts']['overdue']);
        $this->assertSame(1, $data['threads']->total());
        $this->assertSame(1, $data['byCategory']['support']);
    }

    public function test_bulk_checks_private_access_before_any_mutation(): void
    {
        [$u,$ws] = $this->setupInbox();
        $public = $this->thread($u, $ws);
        $private = $this->thread($u, $ws, ['is_private' => true]);
        $viewer = User::create(['name' => 'Viewer', 'email' => Str::random(10).'@example.com', 'password' => bcrypt('test'), 'status' => 'active']);
        $this->actingAs($viewer);
        try {
            app(InboxUnifiedController::class)->bulk(Request::create('/', 'POST', ['action' => 'archive', 'thread_ids' => [$public->id, $private->id]]));
            $this->fail('Must reject private selection');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }$this->assertSame('open', $public->fresh()->status);
    }

    public function test_multiple_channels_and_statuses_combine_without_exposing_private_threads(): void
    {
        [$u, $ws] = $this->setupInbox();
        $this->thread($u, $ws, ['channel' => 'email', 'status' => 'open']);
        $this->thread($u, $ws, ['channel' => 'form', 'status' => 'archived']);
        $this->thread($u, $ws, ['channel' => 'email', 'status' => 'snoozed']);
        $this->thread($u, $ws, ['channel' => 'email', 'is_private' => true]);
        $viewer = User::factory()->create();
        $this->actingAs($viewer);
        $data = app(InboxUnifiedController::class)->index(Request::create('/', 'GET', ['status_multi' => ['open','archived'], 'channel_multi' => ['email','form']]))->getData();
        $this->assertSame(2, $data['threads']->total());
        $this->assertFalse($data['threads']->getCollection()->contains('is_private', true));
    }

    public function test_search_filters_and_informative_rows_render(): void
    {
        [$u,$ws] = $this->setupInbox();
        $this->thread($u, $ws, ['is_starred' => true]);
        $req = Request::create('/', 'GET', ['status' => 'open', 'channel' => 'email', 'category' => 'support', 'q' => 'Subject']);
        $this->app->instance('request', $req);
        $data = app(InboxUnifiedController::class)->index($req)->getData();
        $source = file_get_contents(resource_path('views/user/inbox/unified/index.blade.php'));
        $source = str_replace(["@extends('user.layouts.app')", "@section('title', 'Inbox 2.0')", "@section('content')", '@endsection'], '', $source);
        $html = Blade::render($source, $data);
        $this->assertStringContainsString('name="channel" aria-label="Channels" data-filter-multiple', $html);
        $this->assertStringContainsString('value="open" selected', $html);
        $this->assertStringContainsString('Response due', $html);
        $this->assertStringContainsString('Unassigned', $html);
        $this->assertStringContainsString('data-list-filters',$html);
        if (getenv('INBOX_UI_FIXTURE')) {
            file_put_contents('/tmp/inbox-fixture.html','<!doctype html><html><head><meta charset="utf-8"><link rel="stylesheet" href="/app.css"><style>:root{--bg-card:#fff;--text-primary:#182237;--text-secondary:#4a5570;--text-muted:#66718b;--text-faint:#6c778e;--border-glass:#e1e5ef;--bg-glass-input:#f4f6fb;--accent:#4167ee}body{padding:24px;background:#f7f8fc;font-family:system-ui}.card-premium{background:var(--bg-card);border:1px solid var(--border-glass);border-radius:20px}[x-cloak]{display:none!important}</style></head><body>'.$html.'<script src="/alpine.js" defer></script><script src="/list-design.js"></script></body></html>');
        }
    }
}
