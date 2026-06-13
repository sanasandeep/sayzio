<?php

namespace App\Modules\Api\Controllers;

use App\Modules\Api\Controllers\Concerns\ApiResponses;
use App\Modules\User\Models\QrCode;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\Project;
use App\Modules\User\Support\QrCodeCatalog;
use App\Modules\User\Support\QrCodeDesignSanitizer;
use App\Modules\User\Support\QrCodeTypeRegistry;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class QrCodeController extends Controller
{
    use ApiResponses;

    public function index(Request $request)
    {
        $items = QrCode::where('user_id', $request->user()->id)->orderByDesc('id')->get();
        return $this->ok(['items' => $items->map(fn ($q) => $this->transform($q))->all()]);
    }

    public function show(Request $request, int $id)
    {
        $q = QrCode::where('user_id', $request->user()->id)->find($id);
        if (!$q) return $this->notFound('QR code not found');
        return $this->ok(['qr_code' => $this->transform($q)]);
    }

    /** Catalog of shapes / eyes / frames / fonts / presets shared with the web builder. */
    public function catalog(Request $request)
    {
        return $this->ok([
            'dots'      => QrCodeCatalog::dotShapes(),
            'outer_eyes' => QrCodeCatalog::outerEyeShapes(),
            'inner_eyes' => QrCodeCatalog::innerEyeShapes(),
            'frames'    => QrCodeCatalog::frames(),
            'fonts'     => QrCodeCatalog::fonts(),
            'types'     => QrCodeTypeRegistry::types(),
            'presets'   => QrCodeCatalog::presets(),
            'default_design' => QrCodeDesignSanitizer::defaultDesign(),
        ]);
    }

    public function store(Request $request)
    {
        try {
            $attrs = $this->validatePayload($request, $request->all());
        } catch (ValidationException $e) {
            return $this->fail('Validation failed', 422, 'validation_error', $e->errors());
        }
        $q = new QrCode(array_merge($attrs, ['user_id' => $request->user()->id]));
        $q->workspace_id = $this->activeWorkspaceId($request->user());
        $q->save();
        return $this->created(['qr_code' => $this->transform($q)]);
    }

    public function update(Request $request, int $id)
    {
        $q = QrCode::where('user_id', $request->user()->id)->find($id);
        if (!$q) return $this->notFound('QR code not found');
        try {
            $attrs = $this->validatePayload($request, $request->all(), partial: true, existing: $q);
        } catch (ValidationException $e) {
            return $this->fail('Validation failed', 422, 'validation_error', $e->errors());
        }
        $q->fill($attrs)->save();
        return $this->ok(['qr_code' => $this->transform($q->fresh())]);
    }

    public function destroy(Request $request, int $id)
    {
        $q = QrCode::where('user_id', $request->user()->id)->find($id);
        if (!$q) return $this->notFound('QR code not found');
        $q->delete();
        return $this->noContent();
    }

    /**
     * Bulk-create QR codes in one call. Each item is validated and sanitized
     * independently; the whole batch is rejected if any item is invalid.
     */
    public function bulk(Request $request)
    {
        $items = $request->input('items');
        if (!is_array($items) || !count($items)) {
            return $this->fail('Provide a non-empty "items" array', 422, 'validation_error');
        }
        if (count($items) > 500) {
            return $this->fail('Bulk limit is 500 items per request', 422, 'too_many_items');
        }

        $prepared = [];
        $errors = [];
        foreach ($items as $i => $item) {
            if (!is_array($item)) { $errors[$i] = ['item' => ['Must be an object']]; continue; }
            try {
                $prepared[$i] = $this->validatePayload($request, $item);
            } catch (ValidationException $e) {
                $errors[$i] = $e->errors();
            }
        }
        if ($errors) {
            return $this->fail('One or more items are invalid', 422, 'validation_error', $errors);
        }

        $wsId = $this->activeWorkspaceId($request->user());
        $created = [];
        foreach ($prepared as $attrs) {
            $q = new QrCode(array_merge($attrs, ['user_id' => $request->user()->id]));
            $q->workspace_id = $wsId;
            $q->save();
            $created[] = $this->transform($q);
        }
        return $this->created(['items' => $created, 'count' => count($created)]);
    }

    /**
     * Validate + sanitize a single QR payload (used by store, update, bulk).
     * Mirrors the web builder pipeline exactly via QrCodeDesignSanitizer and
     * QrCodeTypeRegistry so the API and UI never diverge.
     */
    protected function validatePayload(Request $request, array $input, bool $partial = false, ?QrCode $existing = null): array
    {
        $userId = $request->user()->id;
        $typeKeys = array_keys(QrCodeTypeRegistry::types());

        $rules = [
            'name'       => [$partial ? 'sometimes' : 'required', 'string', 'max:160'],
            'type'       => [$partial ? 'sometimes' : 'required', Rule::in($typeKeys)],
            'project_id' => ['nullable', Rule::exists('projects', 'id')->where('user_id', $userId)],
            'link_id'    => ['nullable', Rule::exists('links', 'id')->where('user_id', $userId)],
            'payload'    => ['nullable', 'array'],
            'design'     => ['nullable', 'array'],
        ];
        $base = validator($input, $rules)->validate();

        $type = $base['type'] ?? $existing?->type ?? 'url';
        $hasLink = array_key_exists('link_id', $base) ? !empty($base['link_id']) : (bool) $existing?->link_id;

        // Type-specific payload rules — skipped when the QR is link-backed.
        if (!$hasLink && (array_key_exists('payload', $base) || !$partial)) {
            $payloadRules = collect(QrCodeTypeRegistry::rulesFor($type))
                ->mapWithKeys(fn ($rule, $key) => ["payload.$key" => $rule])
                ->toArray();
            if ($payloadRules) {
                validator($input, $payloadRules)->validate();
            }
        }

        $attrs = [];
        if (array_key_exists('name', $base))       $attrs['name'] = $base['name'];
        if (array_key_exists('type', $base))       $attrs['type'] = $base['type'];
        if (array_key_exists('project_id', $base)) $attrs['project_id'] = $base['project_id'] ?: null;
        if (array_key_exists('link_id', $base))    $attrs['link_id'] = $base['link_id'] ?: null;
        if (array_key_exists('payload', $base))    $attrs['payload'] = (array) ($base['payload'] ?? []);
        if (array_key_exists('design', $input))    $attrs['design'] = QrCodeDesignSanitizer::sanitize((array) ($input['design'] ?? []));

        // On create, ensure design + payload always have a sane baseline.
        if (!$partial) {
            $attrs['payload'] = $attrs['payload'] ?? [];
            $attrs['design']  = $attrs['design'] ?? QrCodeDesignSanitizer::defaultDesign();
        }

        return $attrs;
    }

    protected function transform(QrCode $q): array
    {
        return [
            'id'          => $q->id,
            'name'        => $q->name,
            'type'        => $q->type,
            'link_id'     => $q->link_id,
            'project_id'  => $q->project_id,
            'payload'     => $q->payload,
            'design'      => $q->design,
            'encoded'     => $this->encodedFor($q),
            'preview_url' => $q->preview_url,
            'created_at'  => optional($q->created_at)->toIso8601String(),
        ];
    }

    /** Resolve the string a scanner will see (short link or built payload). */
    protected function encodedFor(QrCode $q): string
    {
        if ($q->link_id && $q->relationLoaded('link') === false) {
            $q->loadMissing('link');
        }
        if ($q->link) {
            return (string) $q->link->getShortUrl();
        }
        try {
            return QrCodeTypeRegistry::buildPayloadString($q->type, (array) $q->payload);
        } catch (\Throwable $e) {
            return '';
        }
    }
}
