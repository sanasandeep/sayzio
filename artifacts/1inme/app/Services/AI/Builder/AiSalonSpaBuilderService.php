<?php

namespace App\Services\AI\Builder;

use App\Modules\User\Models\Link;
use App\Modules\User\Models\User;

class AiSalonSpaBuilderService extends AiServiceBookingBuilderService
{
    public function linkType(): string { return Link::TYPE_SALON_SPA; }
    public function label(): string { return 'Salon & Spa'; }

    protected function systemPrompt(User $user): string
    {
        return parent::systemPrompt($user) . "\nBuild a salon, barbershop or spa catalogue. Use treatments with realistic durations. Represent each hair-length or treatment variant as a separate service. Do not invent medical claims.";
    }
}
