<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Seed a small starter set of page hints and one response template so
 * the assistant has useful page-aware context out of the box. Admins
 * can edit or delete these via the admin UI.
 */
return new class extends Migration {
    public function up(): void
    {
        $now = now();

        foreach ($this->seedHints() as $h) {
            DB::table('site_assistant_page_hints')->updateOrInsert(
                ['route_pattern' => $h['route_pattern'], 'surface' => $h['surface']],
                [
                    'label'             => $h['label'],
                    'description'       => $h['description'],
                    'suggested_actions' => json_encode($h['suggested_actions']),
                    'priority'          => $h['priority'],
                    'is_active'         => true,
                    'created_at'        => $now,
                    'updated_at'        => $now,
                ]
            );
        }

        $tpl = $this->seedTemplate();
        DB::table('site_assistant_response_templates')->updateOrInsert(
            ['key' => $tpl['key']],
            [
                'label'     => $tpl['label'],
                'kind'      => $tpl['kind'],
                'payload'   => json_encode($tpl['payload']),
                'is_active' => true,
                'created_at'=> $now,
                'updated_at'=> $now,
            ]
        );
    }

    public function down(): void
    {
        // Per CONTRIBUTING.md "Backfill / seed migration down() policy":
        // only delete a seeded hint / template if every column we wrote in
        // up() still equals the seeded default. If an admin renamed a hint,
        // edited its description, reordered suggested actions, or rewrote
        // the contact form template via the admin UI, we leave it alone.
        foreach ($this->seedHints() as $h) {
            $row = DB::table('site_assistant_page_hints')
                ->where('route_pattern', $h['route_pattern'])
                ->where('surface', $h['surface'])
                ->first();
            if (!$row) {
                continue;
            }
            $rowActions = json_decode((string) ($row->suggested_actions ?? ''), true);
            // is_active is part of what up() wrote (true). An admin who
            // toggled the hint off in the UI counts as drift and the row
            // must be preserved.
            $matches = $row->label === $h['label']
                && $row->description === $h['description']
                && $rowActions == $h['suggested_actions']
                && (int) $row->priority === $h['priority']
                && (bool) $row->is_active === true;
            if ($matches) {
                DB::table('site_assistant_page_hints')->where('id', $row->id)->delete();
            }
        }

        $tpl = $this->seedTemplate();
        $row = DB::table('site_assistant_response_templates')->where('key', $tpl['key'])->first();
        if ($row) {
            $rowPayload = json_decode((string) ($row->payload ?? ''), true);
            // Same rule: an admin who deactivated the template counts as
            // a real edit and we must not delete it on rollback.
            $matches = $row->label === $tpl['label']
                && $row->kind === $tpl['kind']
                && $rowPayload == $tpl['payload']
                && (bool) $row->is_active === true;
            if ($matches) {
                DB::table('site_assistant_response_templates')->where('id', $row->id)->delete();
            }
        }
    }

    private function seedHints(): array
    {
        return [
            [
                'label'             => 'Pricing page',
                'route_pattern'     => '/pricing*',
                'surface'           => 'marketing',
                'description'       => 'Page where visitors compare 1INME plans. Lead them to start a free trial or contact sales.',
                'suggested_actions' => [['label' => 'Start free trial'], ['label' => 'Compare plans'], ['label' => 'Talk to sales']],
                'priority'          => 10,
            ],
            [
                'label'             => 'Marketing home',
                'route_pattern'     => '/',
                'surface'           => 'marketing',
                'description'       => 'The 1INME marketing landing page introducing the product. Help visitors understand what 1INME is.',
                'suggested_actions' => [['label' => 'What is 1INME?'], ['label' => 'See features'], ['label' => 'Sign up free']],
                'priority'          => 50,
            ],
            [
                'label'             => 'Auth pages',
                'route_pattern'     => 'auth.*',
                'surface'           => 'marketing',
                'description'       => 'Sign-in and sign-up screens. Help visitors with account questions, password resets, and link the right call-to-action.',
                'suggested_actions' => [['label' => 'I forgot my password'], ['label' => 'How do I create an account?']],
                'priority'          => 20,
            ],
            [
                'label'             => 'Billing pages',
                'route_pattern'     => 'billing.*',
                'surface'           => 'app',
                'description'       => 'Where users manage their subscription, plan, and invoices. Help them understand charges or upgrade.',
                'suggested_actions' => [['label' => 'Upgrade my plan'], ['label' => 'See my invoices'], ['label' => 'Cancel subscription']],
                'priority'          => 10,
            ],
            [
                'label'             => 'Dashboard',
                'route_pattern'     => 'dashboard*',
                'surface'           => 'app',
                'description'       => 'The user dashboard summarizing their stats. Help them understand the metrics or jump to relevant features.',
                'suggested_actions' => [['label' => 'What does this metric mean?'], ['label' => 'Take a tour']],
                'priority'          => 30,
            ],
        ];
    }

    private function seedTemplate(): array
    {
        return [
            'key'     => 'contact_handoff',
            'label'   => 'Contact our team (form)',
            'kind'    => 'form',
            'payload' => [
                'fields' => [
                    ['name' => 'name',    'label' => 'Your name',        'type' => 'text',     'required' => true],
                    ['name' => 'email',   'label' => 'Email',            'type' => 'email',    'required' => true],
                    ['name' => 'message', 'label' => 'How can we help?', 'type' => 'textarea', 'required' => true],
                ],
                'submit_label' => 'Send to support',
                'action'       => 'handoff',
            ],
        ];
    }
};
