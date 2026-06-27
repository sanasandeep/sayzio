<?php

namespace App\Modules\Admin\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Admin\Models\AccountBadge;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

/**
 * CRUD for admin-managed account badges (name + color). Listing is gated
 * by `users.view`; create / edit / delete by `users.edit` (enforced at
 * the route layer, mirroring the rest of the user-management suite).
 */
class AccountBadgeController extends Controller
{
    public function index()
    {
        $badges = AccountBadge::query()
            ->withCount('users')
            ->orderBy('name')
            ->get();

        $canManage = (bool) optional(Auth::guard('admin')->user())->hasPermission('users.edit');

        return view('admin.badges.index', compact('badges', 'canManage'));
    }

    public function store(Request $request)
    {
        $data = $this->validateBadge($request);

        AccountBadge::create([
            'name'       => $data['name'],
            'color'      => $this->normalizeColor($data['color']),
            'created_by' => Auth::guard('admin')->id(),
        ]);

        return back()->with('success', 'Badge created.');
    }

    public function update(Request $request, AccountBadge $badge)
    {
        $data = $this->validateBadge($request, $badge);

        $badge->update([
            'name'  => $data['name'],
            'color' => $this->normalizeColor($data['color']),
        ]);

        return back()->with('success', 'Badge updated.');
    }

    public function destroy(AccountBadge $badge)
    {
        // The pivot is declared with cascadeOnDelete, so removing the badge
        // automatically detaches it from every user it was assigned to.
        $badge->delete();

        return back()->with('success', 'Badge deleted.');
    }

    /**
     * @return array{name:string,color:string}
     */
    protected function validateBadge(Request $request, ?AccountBadge $badge = null): array
    {
        return $request->validate([
            'name'  => [
                'required', 'string', 'max:60',
                Rule::unique('account_badges', 'name')->ignore($badge?->id),
            ],
            'color' => ['required', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ], [
            'color.regex' => 'Choose a valid hex color (e.g. #3b82f6).',
        ]);
    }

    protected function normalizeColor(string $color): string
    {
        return strtolower(trim($color));
    }
}
