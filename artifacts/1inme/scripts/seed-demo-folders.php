<?php

use App\Modules\Common\Support\PlatformHosts;
use App\Modules\User\Models\Domain;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\Project;
use App\Modules\User\Models\User;
use Illuminate\Support\Str;

$user = User::where('email', 'sana@sayzio.app')->first();
if (!$user) {
    echo "ERROR: user sana@sayzio.app not found\n";
    return;
}

// Domain binding: bind seeded links to the admin-global primary brand domain
// row (sayzio.app) when it exists, matching links created through the app UI
// on production. NOTE: domain_id NULL would ALSO resolve on sayzio.app — the
// default platform domain's alias namespace includes legacy NULL rows (see
// Link::resolveByAlias + AliasNamespace::scope, pinned by
// SeededLinkDomainResolutionTest) — but binding explicitly keeps seeded rows
// indistinguishable from UI-created ones. Never hardcode a numeric id here;
// row ids differ across environments.
$primaryDomainId = Domain::whereNull('user_id')
    ->where('domain', PlatformHosts::primaryBrandDomain())
    ->value('id');
echo 'Primary brand domain id: ' . var_export($primaryDomainId, true) . "\n";

$workspaceId = optional($user->ownedWorkspaces()->orderBy('id')->first())->id;
echo "User #{$user->id}, workspace: " . var_export($workspaceId, true) . "\n";

$folders = [
    'Marketing' => ['color' => '#2563eb', 'description' => 'Campaign and promo links', 'links' => [
        ['Product Hunt launch', 'https://www.producthunt.com/'],
        ['Sayzio pricing page', 'https://sayzio.app/pricing'],
        ['Features overview', 'https://sayzio.app/features'],
        ['Creators directory', 'https://sayzio.app/creators'],
    ]],
    'Social' => ['color' => '#16a34a', 'description' => 'Social media profiles and posts', 'links' => [
        ['Instagram profile', 'https://www.instagram.com/'],
        ['X / Twitter profile', 'https://twitter.com/'],
        ['YouTube channel', 'https://www.youtube.com/'],
        ['TikTok profile', 'https://www.tiktok.com/'],
    ]],
    'Docs' => ['color' => '#f59e0b', 'description' => 'Guides and documentation', 'links' => [
        ['Sayzio demos gallery', 'https://sayzio.app/demos'],
        ['MDN Web Docs', 'https://developer.mozilla.org/'],
        ['Google Docs', 'https://docs.google.com/'],
    ]],
    'Partners' => ['color' => '#db2777', 'description' => 'Partner and vendor links', 'links' => [
        ['Stripe', 'https://stripe.com/'],
        ['Notion', 'https://www.notion.so/'],
        ['Figma', 'https://www.figma.com/'],
    ]],
];

$mkAlias = function () {
    do {
        $alias = 'demo-' . Str::lower(Str::random(7));
    } while (Link::withoutGlobalScope('workspace')->where('alias', $alias)->exists());
    return $alias;
};

foreach ($folders as $name => $def) {
    $project = Project::withoutGlobalScope('workspace')
        ->where('user_id', $user->id)->where('name', $name)->first();
    if (!$project) {
        $project = new Project([
            'user_id' => $user->id,
            'name' => $name,
            'color' => $def['color'],
            'description' => $def['description'],
        ]);
        $project->workspace_id = $workspaceId;
        $project->save();
        echo "Created folder {$name} (#{$project->id})\n";
    } else {
        echo "Folder {$name} exists (#{$project->id})\n";
    }

    foreach ($def['links'] as [$title, $url]) {
        $exists = Link::withoutGlobalScope('workspace')
            ->where('user_id', $user->id)
            ->where('project_id', $project->id)
            ->where('title', $title)->exists();
        if ($exists) { echo "  link '{$title}' exists\n"; continue; }
        $link = new Link([
            'user_id' => $user->id,
            'project_id' => $project->id,
            'type' => 'url',
        'domain_id' => $primaryDomainId,
            'alias' => $mkAlias(),
            'title' => $title,
            'long_url' => $url,
            'is_active' => true,
        ]);
        $link->workspace_id = $workspaceId;
        $link->save();
        echo "  created link '{$title}' -> /{$link->alias}\n";
    }
}

$unfiled = [
    ['Wikipedia', 'https://www.wikipedia.org/'],
    ['GitHub', 'https://github.com/'],
    ['Sayzio home', 'https://sayzio.app/'],
];
foreach ($unfiled as [$title, $url]) {
    $exists = Link::withoutGlobalScope('workspace')
        ->where('user_id', $user->id)->whereNull('project_id')
        ->where('title', $title)->where('type', 'url')->exists();
    if ($exists) { echo "unfiled link '{$title}' exists\n"; continue; }
    $link = new Link([
        'user_id' => $user->id,
        'type' => 'url',
        'domain_id' => $primaryDomainId,
        'alias' => $mkAlias(),
        'title' => $title,
        'long_url' => $url,
        'is_active' => true,
    ]);
    $link->workspace_id = $workspaceId;
    $link->save();
    echo "created unfiled link '{$title}' -> /{$link->alias}\n";
}

echo "DONE\n";
