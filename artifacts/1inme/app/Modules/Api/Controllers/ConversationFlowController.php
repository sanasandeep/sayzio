<?php

namespace App\Modules\Api\Controllers;

use App\Modules\Api\Controllers\Concerns\ApiResponses;
use App\Modules\User\Controllers\ConversationFlowController as FlowEditor;
use App\Modules\User\Models\ConversationAction;
use App\Modules\User\Models\ConversationStep;
use App\Modules\User\Models\Link;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * Mobile/REST editor for the conversational link family
 * (`links.type = conversational`). Reaches parity with the web
 * ConversationFlowController editor: it authors the flow (steps,
 * branches, tappable choices, end actions) and publishes it.
 *
 * Validation + persistence are delegated to the shared static helpers on
 * the web ConversationFlowController (saveRules / validateFlowData /
 * persistFlow / flowPayload) so web and mobile never drift.
 */
class ConversationFlowController extends Controller
{
    use ApiResponses;

    /** Load the flow definition + editor metadata (catalogs + block targets). */
    public function show(Request $request, int $id)
    {
        $link = $this->ownedBiolinkFamilyLink($request, $id);
        if (!$link) return $this->notFound('Conversational flow not found');

        $flow = FlowEditor::ensureFlow($link);

        $blocks = $link->biolinkBlocks()->whereNull('parent_id')->get(['id', 'type', 'settings']);
        $blockOptions = $blocks->map(fn ($b) => [
            'id'    => (int) $b->id,
            'type'  => $b->type,
            'label' => FlowEditor::blockLabel($b),
        ])->values();

        return $this->ok([
            'flow' => FlowEditor::flowPayload($flow),
            'meta' => [
                'link_id'        => (int) $link->id,
                'alias'          => $link->alias,
                'public_url'     => url('/' . $link->alias),
                'step_kinds'     => ConversationStep::KINDS,
                'action_kinds'   => ConversationAction::KINDS,
                'input_kinds'    => FlowEditor::INPUT_KINDS,
                'condition_ops'  => FlowEditor::CONDITION_OPS,
                'media_kinds'    => ['image', 'gif', 'video', 'audio'],
                'rating_scales'  => ['star', 'nps', 'emoji'],
                'datetime_modes' => ['date', 'time', 'datetime'],
                'blocks'         => $blockOptions,
            ],
        ]);
    }

    /** Replace the flow definition (steps + choices + actions) wholesale. */
    public function save(Request $request, int $id)
    {
        $link = $this->ownedBiolinkFamilyLink($request, $id);
        if (!$link) return $this->notFound('Conversational flow not found');

        $flow = FlowEditor::ensureFlow($link);

        $data = $request->validate(FlowEditor::saveRules());

        if ($err = FlowEditor::validateFlowData($data)) {
            return $this->fail($err, 422, 'invalid_flow');
        }

        FlowEditor::persistFlow($flow, $data);

        return $this->show($request, $id);
    }

    /** Resolve a biolink-family link owned by the signed-in user, or null. */
    protected function ownedBiolinkFamilyLink(Request $request, int $id): ?Link
    {
        $link = Link::where('user_id', $request->user()->id)->find($id);
        if (!$link || !$link->isBiolinkFamily()) {
            return null;
        }
        return $link;
    }
}
