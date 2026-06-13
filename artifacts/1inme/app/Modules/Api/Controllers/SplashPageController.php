<?php

namespace App\Modules\Api\Controllers;

use App\Modules\Api\Controllers\Concerns\ApiResponses;
use App\Modules\User\Models\SplashPage;
use App\Modules\User\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class SplashPageController extends Controller
{
    use ApiResponses;

    public function index(Request $request)
    {
        $items = SplashPage::where('user_id', $request->user()->id)->orderByDesc('id')->get();
        return $this->ok(['items' => $items->map(fn ($s) => $this->transform($s))->all()]);
    }

    public function show(Request $request, int $id)
    {
        $s = SplashPage::where('user_id', $request->user()->id)->find($id);
        if (!$s) return $this->notFound('Splash page not found');
        return $this->ok(['splash_page' => $this->transform($s)]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'          => ['required', 'string', 'max:120'],
            'title'         => ['nullable', 'string', 'max:200'],
            'description'   => ['nullable', 'string', 'max:2000'],
            'cta_label'     => ['nullable', 'string', 'max:80'],
            'cta_url'       => ['nullable', 'url', 'max:2048'],
            'auto_redirect' => ['nullable', 'boolean'],
            'countdown'     => ['nullable', 'integer', 'min:0', 'max:60'],
            'project_id'    => ['nullable', 'integer'],
        ]);
        if (!empty($data['project_id'])) {
            $owns = Project::where('user_id', $request->user()->id)
                ->whereKey($data['project_id'])
                ->exists();
            if (!$owns) return $this->forbidden('You do not own that project');
        }
        $s = new SplashPage(array_merge($data, [
            'user_id'       => $request->user()->id,
            'auto_redirect' => (bool) ($data['auto_redirect'] ?? false),
        ]));
        $s->workspace_id = $this->activeWorkspaceId($request->user());
        $s->save();
        return $this->created(['splash_page' => $this->transform($s)]);
    }

    public function update(Request $request, int $id)
    {
        $s = SplashPage::where('user_id', $request->user()->id)->find($id);
        if (!$s) return $this->notFound('Splash page not found');
        $data = $request->validate([
            'name'          => ['sometimes', 'string', 'max:120'],
            'title'         => ['sometimes', 'nullable', 'string', 'max:200'],
            'description'   => ['sometimes', 'nullable', 'string', 'max:2000'],
            'cta_label'     => ['sometimes', 'nullable', 'string', 'max:80'],
            'cta_url'       => ['sometimes', 'nullable', 'url', 'max:2048'],
            'auto_redirect' => ['sometimes', 'boolean'],
            'countdown'     => ['sometimes', 'nullable', 'integer', 'min:0', 'max:60'],
        ]);
        $s->fill($data)->save();
        return $this->ok(['splash_page' => $this->transform($s)]);
    }

    public function destroy(Request $request, int $id)
    {
        $s = SplashPage::where('user_id', $request->user()->id)->find($id);
        if (!$s) return $this->notFound('Splash page not found');
        $s->delete();
        return $this->noContent();
    }

    protected function transform(SplashPage $s): array
    {
        return [
            'id'            => $s->id,
            'project_id'    => $s->project_id,
            'name'          => $s->name,
            'title'         => $s->title,
            'description'   => $s->description,
            'cta_label'     => $s->cta_label,
            'cta_url'       => $s->cta_url,
            'auto_redirect' => (bool) $s->auto_redirect,
            'countdown'     => (int) ($s->countdown ?? 0),
            'logo'          => $s->logo,
            'favicon'       => $s->favicon,
            'og_image'      => $s->og_image,
            'created_at'    => optional($s->created_at)->toIso8601String(),
        ];
    }
}
