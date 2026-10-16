<?php

namespace App\Modules\Admin\Support;

/**
 * Names for the variants inside each plan category.
 *
 * A visitor sees ONE plan per category and is told it was matched to them, so
 * the variants cannot read as "Bio Lite A / Bio Lite B". Each has to stand on
 * its own as a plan someone could have chosen deliberately. These are written
 * to sit in the same family without being numbered siblings.
 *
 * The first entry in each list is the plan that already exists, so generating
 * variants never renames the anchor out from under its subscribers.
 *
 * Suggestions only: rename any of them in admin afterwards. Nothing in the
 * code matches on a plan's name.
 */
class PlanVariantNames
{
    /** @return array<string, list<string>> */
    public static function pools(): array
    {
        return [
            'bio-entry' => [
                'Bio Lite', 'Bio Start', 'Bio Focus', 'Bio Everyday', 'Bio Simple',
                'Bio Core', 'Bio Essentials', 'Bio Spark', 'Bio One', 'Bio Basics',
            ],
            'bio-advanced' => [
                'Bio Plus', 'Bio Pro', 'Bio Studio', 'Bio Signature', 'Bio Elevate',
                'Bio Prime', 'Bio Craft', 'Bio Edge', 'Bio Complete', 'Bio Max',
            ],
            'platform-solo' => [
                'Creator', 'Creator Studio', 'Creator Flow', 'Creator Core', 'Maker',
                'Maker Pro', 'Solo Studio', 'Creator Suite', 'Creator Edge', 'Creator Prime',
            ],
            'platform-pro' => [
                'Professional', 'Pro Suite', 'Pro Studio', 'Operator', 'Pro Growth',
                'Pro Complete', 'Pro Signature', 'Business Pro', 'Pro Edge', 'Pro Max',
            ],
            'team-small' => [
                'Business', 'Team', 'Team Pro', 'Workgroup', 'Business Suite',
                'Team Studio', 'Collective', 'Business Core', 'Team Edge', 'Business Plus',
            ],
            'team-agency' => [
                'Agency', 'Agency Pro', 'Studio', 'Agency Suite', 'Partner',
                'White Label', 'Agency Core', 'Studio Pro', 'Agency Edge', 'Agency Max',
            ],
            'dev-standard' => [
                'Developer', 'Builder', 'Dev Pro', 'API Standard', 'Integrator',
                'Dev Suite', 'Builder Pro', 'Dev Core', 'API Builder', 'Dev Edge',
            ],
            'dev-enterprise' => [
                'Enterprise API', 'Scale', 'Platform API', 'Enterprise Suite', 'Infrastructure',
                'API Enterprise', 'Enterprise Core', 'Scale Pro', 'Enterprise Edge', 'API Max',
            ],
        ];
    }

    /**
     * Price multipliers applied to the anchor, index-matched to the names
     * above. The anchor keeps its own price (1.00); the rest sit within ±15%,
     * which is wide enough to learn something from and narrow enough that two
     * people comparing notes see a difference they can shrug at rather than
     * one that looks like a different product.
     *
     * @return list<float>
     */
    public static function multipliers(): array
    {
        return [1.00, 0.94, 1.06, 0.97, 1.09, 0.91, 1.03, 1.12, 0.88, 1.15];
    }
}
