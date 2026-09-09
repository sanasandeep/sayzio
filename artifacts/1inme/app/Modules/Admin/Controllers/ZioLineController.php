<?php

namespace App\Modules\Admin\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Admin\Models\ZioLine;
use Illuminate\Http\Request;

/**
 * Admin editor for the lines Zio speaks in the homepage hero bubble.
 *
 * One page rather than the usual index/create/edit trio: a line is a single
 * short string, and sending someone to a separate screen to change four
 * words is a worse trade than an inline row. The routes are still the plain
 * REST set, so this behaves like every other content editor in the admin.
 *
 * Every mutation flushes the cache the hero reads (see ZioLine), so an edit
 * is live on the next page load instead of up to five minutes later.
 */
class ZioLineController extends Controller
{
    public function index()
    {
        $lines = ZioLine::query()->ordered()->get();

        return view('admin.zio-lines.index', compact('lines'));
    }

    public function store(Request $request)
    {
        ZioLine::create($this->validated($request));
        ZioLine::flushCache();

        return redirect()->route('admin.zio-lines.index')->with('success', 'Line added.');
    }

    public function update(Request $request, ZioLine $zioLine)
    {
        $zioLine->update($this->validated($request));
        ZioLine::flushCache();

        return redirect()->route('admin.zio-lines.index')->with('success', 'Line updated.');
    }

    public function destroy(ZioLine $zioLine)
    {
        $zioLine->delete();
        ZioLine::flushCache();

        return redirect()->route('admin.zio-lines.index')->with('success', 'Line deleted.');
    }

    public function toggle(ZioLine $zioLine)
    {
        $zioLine->update(['is_active' => ! $zioLine->is_active]);
        ZioLine::flushCache();

        return back()->with('success', $zioLine->is_active ? 'Line shown.' : 'Line hidden.');
    }

    /**
     * max is ZioLine::MAX_LENGTH, not the column's 120: the bubble is a fixed
     * ellipse, so a line has to fit inside a curve. The column has headroom
     * on purpose -- the shape is the real constraint and it should be the one
     * that reports the error.
     */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'text'       => ['required', 'string', 'max:' . ZioLine::MAX_LENGTH],
            'is_active'  => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:99999'],
        ], [
            'text.max' => 'Keep a line to :max characters or it will not fit inside the bubble.',
        ]);

        $data['is_active']  = (bool) $request->input('is_active', false);
        $data['sort_order'] = $data['sort_order'] ?? 0;

        return $data;
    }
}
