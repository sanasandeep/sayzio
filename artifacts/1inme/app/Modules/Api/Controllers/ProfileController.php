<?php

namespace App\Modules\Api\Controllers;

use App\Modules\Api\Controllers\Concerns\ApiResponses;
use App\Modules\Api\Resources\UserResource;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;

class ProfileController extends Controller
{
    use ApiResponses;

    public function show(Request $request)
    {
        return $this->ok(['user' => UserResource::toArray($request->user(), self: true)]);
    }

    public function update(Request $request)
    {
        $user = $request->user();
        $data = $request->validate([
            'name'             => ['sometimes', 'string', 'max:120'],
            'bio'              => ['sometimes', 'nullable', 'string', 'max:500'],
            'handle'           => ['sometimes', 'nullable', 'string', 'max:60', 'regex:/^[a-z0-9_]+$/i', Rule::unique('users', 'handle')->ignore($user->id)],
            'avatar'           => ['sometimes', 'nullable', 'string', 'max:500'],
            'phone'            => ['sometimes', 'nullable', 'string', 'max:40'],
            'timezone'         => ['sometimes', 'nullable', 'string', 'max:60'],
            'language'         => ['sometimes', 'nullable', 'string', 'max:10'],
            'discoverable'     => ['sometimes', 'boolean'],
            'allow_followers'  => ['sometimes', 'boolean'],
        ]);

        $user->fill($data)->save();
        return $this->ok(['user' => UserResource::toArray($user->fresh(), self: true)]);
    }
}
