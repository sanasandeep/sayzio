<?php

namespace App\Modules\Api\Resources;

use App\Modules\User\Models\User;

class UserResource
{
    public static function toArray(User $u, bool $self = false): array
    {
        $base = [
            'id'              => $u->id,
            'name'            => $u->name,
            'handle'          => $u->handle,
            'avatar'          => $u->avatar,
            'bio'             => $u->bio,
            'discoverable'    => (bool) $u->discoverable,
            'allow_followers' => (bool) $u->allow_followers,
            'followers_count' => (int)  $u->followers_count,
        ];
        if ($self) {
            $base = array_merge($base, [
                'email'             => $u->email,
                'phone'             => $u->phone,
                'timezone'          => $u->timezone,
                'language'          => $u->language,
                'plan_id'           => $u->plan_id,
                'billing_cycle'     => $u->billing_cycle,
                'plan_expires_at'   => optional($u->plan_expires_at)->toIso8601String(),
                'trial_ends_at'     => optional($u->trial_ends_at)->toIso8601String(),
                'role'              => $u->role,
                'status'            => $u->status,
                'email_verified_at' => optional($u->email_verified_at)->toIso8601String(),
                'onboarded_at'      => optional($u->onboarded_at)->toIso8601String(),
                'created_at'        => optional($u->created_at)->toIso8601String(),
            ]);
        }
        return $base;
    }
}
