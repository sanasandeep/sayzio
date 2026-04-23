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

        $hints = [
            ['Pricing page',  '/pricing*',          'marketing',
                'Page where visitors compare 1INME plans. Lead them to start a free trial or contact sales.',
                [['label' => 'Start free trial'], ['label' => 'Compare plans'], ['label' => 'Talk to sales']],
                10],
            ['Marketing home', '/',                 'marketing',
                'The 1INME marketing landing page introducing the product. Help visitors understand what 1INME is.',
                [['label' => 'What is 1INME?'], ['label' => 'See features'], ['label' => 'Sign up free']],
                50],
            ['Auth pages',    'auth.*',             'marketing',
                'Sign-in and sign-up screens. Help visitors with account questions, password resets, and link the right call-to-action.',
                [['label' => 'I forgot my password'], ['label' => 'How do I create an account?']],
                20],
            ['Billing pages', 'billing.*',          'app',
                'Where users manage their subscription, plan, and invoices. Help them understand charges or upgrade.',
                [['label' => 'Upgrade my plan'], ['label' => 'See my invoices'], ['label' => 'Cancel subscription']],
                10],
            ['Dashboard',     'dashboard*',         'app',
                'The user dashboard summarizing their stats. Help them understand the metrics or jump to relevant features.',
                [['label' => 'What does this metric mean?'], ['label' => 'Take a tour']],
                30],
        ];

        foreach ($hints as $i => $h) {
            DB::table('site_assistant_page_hints')->updateOrInsert(
                ['route_pattern' => $h[1], 'surface' => $h[2]],
                [
                    'label'             => $h[0],
                    'description'       => $h[3],
                    'suggested_actions' => json_encode($h[4]),
                    'priority'          => $h[5],
                    'is_active'         => true,
                    'created_at'        => $now,
                    'updated_at'        => $now,
                ]
            );
        }

        DB::table('site_assistant_response_templates')->updateOrInsert(
            ['key' => 'contact_handoff'],
            [
                'label'     => 'Contact our team (form)',
                'kind'      => 'form',
                'payload'   => json_encode([
                    'fields' => [
                        ['name' => 'name',    'label' => 'Your name',    'type' => 'text',     'required' => true],
                        ['name' => 'email',   'label' => 'Email',         'type' => 'email',    'required' => true],
                        ['name' => 'message', 'label' => 'How can we help?', 'type' => 'textarea', 'required' => true],
                    ],
                    'submit_label' => 'Send to support',
                    'action'       => 'handoff',
                ]),
                'is_active' => true,
                'created_at'=> $now,
                'updated_at'=> $now,
            ]
        );
    }

    public function down(): void
    {
        DB::table('site_assistant_response_templates')->where('key', 'contact_handoff')->delete();
        DB::table('site_assistant_page_hints')->whereIn('route_pattern', [
            '/pricing*', '/', 'auth.*', 'billing.*', 'dashboard*'
        ])->delete();
    }
};
