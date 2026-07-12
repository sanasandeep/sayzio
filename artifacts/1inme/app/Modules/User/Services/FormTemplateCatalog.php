<?php

namespace App\Modules\User\Services;

/**
 * Single source of truth for all curated form templates.
 *
 * Each template has:
 *   key      — unique identifier (used in DB + controller match)
 *   label    — display name
 *   desc     — one-line description shown on the picker card
 *   icon     — Font Awesome class (fas fa-*)
 *   category — audience/use-case group key
 *   fields   — array of field definitions (only existing supported types)
 *
 * Category order controls the display order in the UI.
 */
class FormTemplateCatalog
{
    /** @return array<string, array{label:string,icon:string,blurb:string}> */
    public static function categories(): array
    {
        return [
            'general'       => ['label' => 'General',                  'icon' => 'fa-layer-group'],
            'business'      => ['label' => 'Business',                 'icon' => 'fa-briefcase'],
            'creators'      => ['label' => 'Creators',                 'icon' => 'fa-star'],
            'events'        => ['label' => 'Events',                   'icon' => 'fa-calendar-check'],
            'food'          => ['label' => 'Restaurants & Food',       'icon' => 'fa-utensils'],
            'health'        => ['label' => 'Health & Wellness',        'icon' => 'fa-heart-pulse'],
            'education'     => ['label' => 'Education',                'icon' => 'fa-graduation-cap'],
            'real_estate'   => ['label' => 'Real Estate',              'icon' => 'fa-house'],
            'nonprofit'     => ['label' => 'Nonprofit & Community',    'icon' => 'fa-people-group'],
            'freelance'     => ['label' => 'Freelancers & Services',   'icon' => 'fa-user-tie'],
            'retail'        => ['label' => 'Retail & E-commerce',      'icon' => 'fa-bag-shopping'],
        ];
    }

    /**
     * All templates. Keys must be unique across the full list.
     *
     * @return array<string, array{label:string,desc:string,icon:string,category:string,fields:array}>
     */
    public static function all(): array
    {
        return [
            // ── General ─────────────────────────────────────────────────────
            'contact' => [
                'label'    => 'Contact',
                'desc'     => 'Name + email + message',
                'icon'     => 'fa-envelope',
                'category' => 'general',
                'fields'   => [
                    ['id' => 'name',    'type' => 'text',     'label' => 'Full Name',     'required' => true],
                    ['id' => 'email',   'type' => 'email',    'label' => 'Email',          'required' => true],
                    ['id' => 'subject', 'type' => 'text',     'label' => 'Subject',        'required' => false],
                    ['id' => 'message', 'type' => 'textarea', 'label' => 'Message',        'required' => true, 'rows' => 5],
                ],
            ],
            'lead' => [
                'label'    => 'Lead Capture',
                'desc'     => 'Name, email, phone, budget',
                'icon'     => 'fa-bullseye',
                'category' => 'general',
                'fields'   => [
                    ['id' => 'name',    'type' => 'text',     'label' => 'Full Name',                  'required' => true],
                    ['id' => 'email',   'type' => 'email',    'label' => 'Work Email',                 'required' => true],
                    ['id' => 'phone',   'type' => 'phone',    'label' => 'Phone',                      'required' => false],
                    ['id' => 'company', 'type' => 'text',     'label' => 'Company',                    'required' => false],
                    ['id' => 'budget',  'type' => 'select',   'label' => 'Budget',                     'required' => false,
                        'options' => ['< $1k', '$1k–$5k', '$5k–$25k', '$25k+']],
                    ['id' => 'notes',   'type' => 'textarea', 'label' => 'Tell us about your project', 'rows' => 4],
                ],
            ],
            'survey' => [
                'label'    => 'Survey',
                'desc'     => 'Ratings & open feedback',
                'icon'     => 'fa-poll',
                'category' => 'general',
                'fields'   => [
                    ['id' => 'satisfaction', 'type' => 'rating',   'label' => 'How satisfied are you?',            'max' => 5,  'required' => true],
                    ['id' => 'recommend',    'type' => 'scale',    'label' => 'How likely are you to recommend us?','min' => 0,  'max' => 10, 'required' => true],
                    ['id' => 'comments',     'type' => 'textarea', 'label' => 'Any additional comments?',           'rows' => 4],
                ],
            ],
            'registration' => [
                'label'    => 'Registration',
                'desc'     => 'Sign-up with consent',
                'icon'     => 'fa-user-plus',
                'category' => 'general',
                'fields'   => [
                    ['id' => 'name',       'type' => 'text',    'label' => 'Full Name',          'required' => true],
                    ['id' => 'email',      'type' => 'email',   'label' => 'Email',               'required' => true],
                    ['id' => 'event_date', 'type' => 'date',    'label' => 'Preferred Date',      'required' => true],
                    ['id' => 'attendees',  'type' => 'number',  'label' => 'Number of Attendees', 'min' => 1, 'required' => true],
                    ['id' => 'consent',    'type' => 'consent', 'label' => 'I agree to the terms and privacy policy', 'required' => true],
                ],
            ],
            'feedback' => [
                'label'    => 'Feedback',
                'desc'     => 'Rating + category',
                'icon'     => 'fa-comment-dots',
                'category' => 'general',
                'fields'   => [
                    ['id' => 'rating',    'type' => 'rating',   'label' => 'Rate your experience',    'max' => 5, 'required' => true],
                    ['id' => 'category',  'type' => 'radio',    'label' => 'What is this about?',
                        'options' => ['Bug', 'Suggestion', 'Compliment', 'Other'], 'required' => true],
                    ['id' => 'message',   'type' => 'textarea', 'label' => 'Your message',            'required' => true, 'rows' => 4],
                    ['id' => 'email',     'type' => 'email',    'label' => 'Email (optional)',         'required' => false],
                ],
            ],
            'blank' => [
                'label'    => 'Blank',
                'desc'     => 'Start from scratch',
                'icon'     => 'fa-file',
                'category' => 'general',
                'fields'   => [],
            ],

            // ── Business ────────────────────────────────────────────────────
            'job_application' => [
                'label'    => 'Job Application',
                'desc'     => 'Resume + cover letter upload',
                'icon'     => 'fa-id-card',
                'category' => 'business',
                'fields'   => [
                    ['id' => 'name',         'type' => 'text',     'label' => 'Full Name',         'required' => true],
                    ['id' => 'email',        'type' => 'email',    'label' => 'Email',              'required' => true],
                    ['id' => 'phone',        'type' => 'phone',    'label' => 'Phone',              'required' => false],
                    ['id' => 'position',     'type' => 'text',     'label' => 'Position Applied For','required' => true],
                    ['id' => 'linkedin',     'type' => 'url',      'label' => 'LinkedIn Profile',   'required' => false],
                    ['id' => 'resume',       'type' => 'file',     'label' => 'Upload Resume',      'required' => true],
                    ['id' => 'cover_letter', 'type' => 'textarea', 'label' => 'Cover Letter',       'required' => false, 'rows' => 6],
                    ['id' => 'consent',      'type' => 'consent',  'label' => 'I consent to my data being stored for recruitment purposes', 'required' => true],
                ],
            ],
            'partnership' => [
                'label'    => 'Partnership Inquiry',
                'desc'     => 'Collaboration & partnership requests',
                'icon'     => 'fa-handshake',
                'category' => 'business',
                'fields'   => [
                    ['id' => 'name',        'type' => 'text',     'label' => 'Your Name',          'required' => true],
                    ['id' => 'company',     'type' => 'text',     'label' => 'Company / Brand',    'required' => true],
                    ['id' => 'email',       'type' => 'email',    'label' => 'Business Email',     'required' => true],
                    ['id' => 'website',     'type' => 'url',      'label' => 'Website',            'required' => false],
                    ['id' => 'type',        'type' => 'select',   'label' => 'Partnership Type',   'required' => true,
                        'options' => ['Co-marketing', 'Reseller / Affiliate', 'Technology Integration', 'Strategic Alliance', 'Other']],
                    ['id' => 'proposal',    'type' => 'textarea', 'label' => 'Describe your proposal', 'required' => true, 'rows' => 5],
                ],
            ],
            'nps' => [
                'label'    => 'NPS Survey',
                'desc'     => 'Net Promoter Score + why',
                'icon'     => 'fa-chart-line',
                'category' => 'business',
                'fields'   => [
                    ['id' => 'score',  'type' => 'scale',    'label' => 'How likely are you to recommend us to a friend or colleague?', 'min' => 0, 'max' => 10, 'required' => true],
                    ['id' => 'reason', 'type' => 'textarea', 'label' => 'What\'s the main reason for your score?', 'rows' => 4, 'required' => false],
                    ['id' => 'improve','type' => 'textarea', 'label' => 'What could we do better?', 'rows' => 3, 'required' => false],
                    ['id' => 'email',  'type' => 'email',    'label' => 'Email (optional — if you\'d like us to follow up)', 'required' => false],
                ],
            ],
            'vendor_onboarding' => [
                'label'    => 'Vendor Onboarding',
                'desc'     => 'Supplier registration & info',
                'icon'     => 'fa-truck',
                'category' => 'business',
                'fields'   => [
                    ['id' => 'company',      'type' => 'text',    'label' => 'Company Name',        'required' => true],
                    ['id' => 'contact_name', 'type' => 'text',    'label' => 'Contact Person',      'required' => true],
                    ['id' => 'email',        'type' => 'email',   'label' => 'Email',               'required' => true],
                    ['id' => 'phone',        'type' => 'phone',   'label' => 'Phone',               'required' => true],
                    ['id' => 'category',     'type' => 'select',  'label' => 'Product/Service Category','required' => true,
                        'options' => ['Goods', 'Services', 'Software/Technology', 'Raw Materials', 'Logistics', 'Other']],
                    ['id' => 'tax_id',       'type' => 'text',    'label' => 'Tax ID / VAT Number', 'required' => false],
                    ['id' => 'documents',    'type' => 'file',    'label' => 'Upload Business Documents', 'required' => false],
                    ['id' => 'terms',        'type' => 'consent', 'label' => 'I agree to the vendor terms and conditions', 'required' => true],
                ],
            ],
            'customer_complaint' => [
                'label'    => 'Customer Complaint',
                'desc'     => 'Structured complaint resolution',
                'icon'     => 'fa-triangle-exclamation',
                'category' => 'business',
                'fields'   => [
                    ['id' => 'name',         'type' => 'text',     'label' => 'Full Name',          'required' => true],
                    ['id' => 'email',        'type' => 'email',    'label' => 'Email',              'required' => true],
                    ['id' => 'order_number', 'type' => 'text',     'label' => 'Order / Reference Number', 'required' => false],
                    ['id' => 'category',     'type' => 'select',   'label' => 'Issue Type',         'required' => true,
                        'options' => ['Product quality', 'Delivery issue', 'Billing/charge', 'Customer service', 'Website issue', 'Other']],
                    ['id' => 'description',  'type' => 'textarea', 'label' => 'Describe the issue', 'required' => true, 'rows' => 5],
                    ['id' => 'resolution',   'type' => 'radio',    'label' => 'What resolution would you like?',
                        'options' => ['Refund', 'Replacement', 'Store credit', 'Apology / explanation'], 'required' => true],
                    ['id' => 'attachment',   'type' => 'file',     'label' => 'Attach evidence (photo, screenshot)', 'required' => false],
                ],
            ],
            'meeting_request' => [
                'label'    => 'Meeting Request',
                'desc'     => 'Book a call or in-person meeting',
                'icon'     => 'fa-calendar-days',
                'category' => 'business',
                'fields'   => [
                    ['id' => 'name',    'type' => 'text',     'label' => 'Your Name',            'required' => true],
                    ['id' => 'email',   'type' => 'email',    'label' => 'Email',                'required' => true],
                    ['id' => 'company', 'type' => 'text',     'label' => 'Company',              'required' => false],
                    ['id' => 'date',    'type' => 'date',     'label' => 'Preferred Date',       'required' => true],
                    ['id' => 'time',    'type' => 'time',     'label' => 'Preferred Time',       'required' => true],
                    ['id' => 'format',  'type' => 'radio',    'label' => 'Meeting Format',
                        'options' => ['Video call', 'Phone call', 'In-person'], 'required' => true],
                    ['id' => 'agenda',  'type' => 'textarea', 'label' => 'What would you like to discuss?', 'rows' => 3, 'required' => false],
                ],
            ],

            // ── Creators ────────────────────────────────────────────────────
            'collaboration' => [
                'label'    => 'Collaboration Request',
                'desc'     => 'Brand deals & collabs',
                'icon'     => 'fa-star',
                'category' => 'creators',
                'fields'   => [
                    ['id' => 'name',      'type' => 'text',     'label' => 'Your Name',              'required' => true],
                    ['id' => 'brand',     'type' => 'text',     'label' => 'Brand / Company Name',   'required' => true],
                    ['id' => 'email',     'type' => 'email',    'label' => 'Email',                  'required' => true],
                    ['id' => 'platform',  'type' => 'checkbox', 'label' => 'Platforms',
                        'options' => ['Instagram', 'TikTok', 'YouTube', 'Twitter / X', 'Blog', 'Podcast', 'Other'], 'required' => true],
                    ['id' => 'budget',    'type' => 'select',   'label' => 'Campaign Budget',        'required' => false,
                        'options' => ['< $500', '$500–$2k', '$2k–$10k', '$10k+', 'Gifting / exchange']],
                    ['id' => 'details',   'type' => 'textarea', 'label' => 'Tell me about your campaign idea', 'required' => true, 'rows' => 5],
                    ['id' => 'brief',     'type' => 'file',     'label' => 'Attach creative brief (optional)', 'required' => false],
                ],
            ],
            'fan_mail' => [
                'label'    => 'Fan Mail',
                'desc'     => 'Let your audience reach you',
                'icon'     => 'fa-heart',
                'category' => 'creators',
                'fields'   => [
                    ['id' => 'name',    'type' => 'text',     'label' => 'Your Name',              'required' => true],
                    ['id' => 'email',   'type' => 'email',    'label' => 'Email (optional)',       'required' => false],
                    ['id' => 'message', 'type' => 'textarea', 'label' => 'Your message',           'required' => true, 'rows' => 6],
                    ['id' => 'social',  'type' => 'url',      'label' => 'Your social profile (optional)', 'required' => false],
                ],
            ],
            'press_kit_request' => [
                'label'    => 'Press Kit Request',
                'desc'     => 'Media & journalist inquiries',
                'icon'     => 'fa-newspaper',
                'category' => 'creators',
                'fields'   => [
                    ['id' => 'name',        'type' => 'text',     'label' => 'Your Name',          'required' => true],
                    ['id' => 'outlet',      'type' => 'text',     'label' => 'Publication / Outlet','required' => true],
                    ['id' => 'email',       'type' => 'email',    'label' => 'Email',              'required' => true],
                    ['id' => 'article',     'type' => 'text',     'label' => 'Article / Topic',    'required' => false],
                    ['id' => 'deadline',    'type' => 'date',     'label' => 'Deadline',           'required' => false],
                    ['id' => 'notes',       'type' => 'textarea', 'label' => 'What do you need?',  'required' => true, 'rows' => 4],
                ],
            ],
            'content_idea' => [
                'label'    => 'Content Idea Submission',
                'desc'     => 'Crowdsource ideas from your audience',
                'icon'     => 'fa-lightbulb',
                'category' => 'creators',
                'fields'   => [
                    ['id' => 'name',    'type' => 'text',     'label' => 'Your Name',             'required' => false],
                    ['id' => 'topic',   'type' => 'text',     'label' => 'Topic / Title Idea',    'required' => true],
                    ['id' => 'format',  'type' => 'radio',    'label' => 'Preferred Format',
                        'options' => ['Video', 'Podcast episode', 'Blog post', 'Livestream', 'Short / Reel', 'Other'], 'required' => false],
                    ['id' => 'details', 'type' => 'textarea', 'label' => 'Tell me more',          'required' => false, 'rows' => 4],
                ],
            ],
            'speaker_booking' => [
                'label'    => 'Speaker Booking',
                'desc'     => 'Book me for speaking engagements',
                'icon'     => 'fa-microphone',
                'category' => 'creators',
                'fields'   => [
                    ['id' => 'name',      'type' => 'text',     'label' => 'Organizer Name',       'required' => true],
                    ['id' => 'org',       'type' => 'text',     'label' => 'Organization',         'required' => true],
                    ['id' => 'email',     'type' => 'email',    'label' => 'Email',                'required' => true],
                    ['id' => 'event',     'type' => 'text',     'label' => 'Event Name',           'required' => true],
                    ['id' => 'date',      'type' => 'date',     'label' => 'Event Date',           'required' => true],
                    ['id' => 'format',    'type' => 'radio',    'label' => 'Format',
                        'options' => ['In-person', 'Virtual', 'Hybrid'], 'required' => true],
                    ['id' => 'audience',  'type' => 'number',   'label' => 'Expected Audience Size','required' => false],
                    ['id' => 'topic',     'type' => 'textarea', 'label' => 'Preferred topic or session brief', 'required' => false, 'rows' => 4],
                ],
            ],

            // ── Events ──────────────────────────────────────────────────────
            'event_rsvp' => [
                'label'    => 'Event RSVP',
                'desc'     => 'Attendance + dietary needs',
                'icon'     => 'fa-calendar-check',
                'category' => 'events',
                'fields'   => [
                    ['id' => 'name',      'type' => 'text',     'label' => 'Full Name',          'required' => true],
                    ['id' => 'email',     'type' => 'email',    'label' => 'Email',              'required' => true],
                    ['id' => 'attending', 'type' => 'radio',    'label' => 'Will you attend?',
                        'options' => ['Yes, I\'ll be there', 'No, I can\'t make it', 'Maybe'], 'required' => true],
                    ['id' => 'guests',    'type' => 'number',   'label' => 'Number of additional guests', 'min' => 0, 'required' => false],
                    ['id' => 'dietary',   'type' => 'checkbox', 'label' => 'Dietary requirements',
                        'options' => ['Vegetarian', 'Vegan', 'Gluten-free', 'Halal', 'Kosher', 'None'], 'required' => false],
                    ['id' => 'notes',     'type' => 'textarea', 'label' => 'Any other notes?',  'rows' => 3, 'required' => false],
                ],
            ],
            'workshop_signup' => [
                'label'    => 'Workshop Sign-up',
                'desc'     => 'Skill level + session preferences',
                'icon'     => 'fa-chalkboard-user',
                'category' => 'events',
                'fields'   => [
                    ['id' => 'name',      'type' => 'text',    'label' => 'Full Name',               'required' => true],
                    ['id' => 'email',     'type' => 'email',   'label' => 'Email',                   'required' => true],
                    ['id' => 'phone',     'type' => 'phone',   'label' => 'Phone',                   'required' => false],
                    ['id' => 'session',   'type' => 'select',  'label' => 'Preferred Session',       'required' => true,
                        'options' => ['Session 1 — Morning', 'Session 2 — Afternoon', 'Session 3 — Evening', 'Either / any']],
                    ['id' => 'level',     'type' => 'radio',   'label' => 'Experience Level',
                        'options' => ['Beginner', 'Intermediate', 'Advanced'], 'required' => true],
                    ['id' => 'goals',     'type' => 'textarea','label' => 'What do you hope to learn?', 'rows' => 3, 'required' => false],
                    ['id' => 'consent',   'type' => 'consent', 'label' => 'I agree to the workshop terms', 'required' => true],
                ],
            ],
            'volunteer_signup' => [
                'label'    => 'Volunteer Sign-up',
                'desc'     => 'Availability & skills',
                'icon'     => 'fa-hands-helping',
                'category' => 'events',
                'fields'   => [
                    ['id' => 'name',         'type' => 'text',     'label' => 'Full Name',           'required' => true],
                    ['id' => 'email',        'type' => 'email',    'label' => 'Email',               'required' => true],
                    ['id' => 'phone',        'type' => 'phone',    'label' => 'Phone',               'required' => false],
                    ['id' => 'availability', 'type' => 'checkbox', 'label' => 'Availability',
                        'options' => ['Friday evening', 'Saturday morning', 'Saturday afternoon', 'Sunday', 'Flexible'], 'required' => true],
                    ['id' => 'skills',       'type' => 'checkbox', 'label' => 'Relevant skills',
                        'options' => ['Logistics', 'Registration desk', 'Technical/AV', 'Social media', 'First aid', 'Other'], 'required' => false],
                    ['id' => 'experience',   'type' => 'textarea', 'label' => 'Prior volunteer experience', 'rows' => 3, 'required' => false],
                    ['id' => 'consent',      'type' => 'consent',  'label' => 'I agree to the volunteer code of conduct', 'required' => true],
                ],
            ],
            'wedding_rsvp' => [
                'label'    => 'Wedding RSVP',
                'desc'     => 'Guest list + meal & song requests',
                'icon'     => 'fa-champagne-glasses',
                'category' => 'events',
                'fields'   => [
                    ['id' => 'name',      'type' => 'text',     'label' => 'Full Name(s)',          'required' => true],
                    ['id' => 'email',     'type' => 'email',    'label' => 'Email',                 'required' => false],
                    ['id' => 'attending', 'type' => 'radio',    'label' => 'Will you attend?',
                        'options' => ['Joyfully accepts', 'Regretfully declines'], 'required' => true],
                    ['id' => 'guests',    'type' => 'number',   'label' => 'Number attending (including yourself)', 'min' => 0, 'required' => false],
                    ['id' => 'meal',      'type' => 'select',   'label' => 'Meal preference',       'required' => false,
                        'options' => ['Chicken', 'Fish', 'Vegetarian', 'Vegan', 'Kids meal']],
                    ['id' => 'dietary',   'type' => 'text',     'label' => 'Dietary restrictions / allergies', 'required' => false],
                    ['id' => 'song',      'type' => 'text',     'label' => 'Song request 🎶',       'required' => false],
                    ['id' => 'message',   'type' => 'textarea', 'label' => 'Message for the couple','rows' => 3, 'required' => false],
                ],
            ],
            'conference_abstract' => [
                'label'    => 'Conference Abstract',
                'desc'     => 'Talk / paper submission',
                'icon'     => 'fa-file-lines',
                'category' => 'events',
                'fields'   => [
                    ['id' => 'presenter',    'type' => 'text',     'label' => 'Presenter Name',        'required' => true],
                    ['id' => 'email',        'type' => 'email',    'label' => 'Email',                 'required' => true],
                    ['id' => 'affiliation',  'type' => 'text',     'label' => 'Affiliation / Organisation','required' => false],
                    ['id' => 'title',        'type' => 'text',     'label' => 'Submission Title',      'required' => true],
                    ['id' => 'track',        'type' => 'select',   'label' => 'Track / Category',      'required' => true,
                        'options' => ['Research Paper', 'Case Study', 'Workshop', 'Panel Discussion', 'Keynote Pitch', 'Poster']],
                    ['id' => 'abstract',     'type' => 'textarea', 'label' => 'Abstract (max 300 words)','required' => true, 'rows' => 8],
                    ['id' => 'bio',          'type' => 'textarea', 'label' => 'Speaker Bio (100 words)','required' => false, 'rows' => 4],
                ],
            ],

            // ── Restaurants & Food ───────────────────────────────────────────
            'table_reservation' => [
                'label'    => 'Table Reservation',
                'desc'     => 'Date, time, party size',
                'icon'     => 'fa-utensils',
                'category' => 'food',
                'fields'   => [
                    ['id' => 'name',     'type' => 'text',     'label' => 'Full Name',          'required' => true],
                    ['id' => 'email',    'type' => 'email',    'label' => 'Email',              'required' => false],
                    ['id' => 'phone',    'type' => 'phone',    'label' => 'Phone',              'required' => true],
                    ['id' => 'date',     'type' => 'date',     'label' => 'Date',               'required' => true],
                    ['id' => 'time',     'type' => 'time',     'label' => 'Time',               'required' => true],
                    ['id' => 'guests',   'type' => 'number',   'label' => 'Party size',         'min' => 1, 'required' => true],
                    ['id' => 'occasion', 'type' => 'select',   'label' => 'Special occasion?',  'required' => false,
                        'options' => ['None', 'Birthday', 'Anniversary', 'Business dinner', 'Other']],
                    ['id' => 'requests', 'type' => 'textarea', 'label' => 'Special requests / dietary notes', 'rows' => 3, 'required' => false],
                ],
            ],
            'catering_inquiry' => [
                'label'    => 'Catering Inquiry',
                'desc'     => 'Event catering quote request',
                'icon'     => 'fa-bowl-food',
                'category' => 'food',
                'fields'   => [
                    ['id' => 'name',     'type' => 'text',     'label' => 'Your Name',            'required' => true],
                    ['id' => 'email',    'type' => 'email',    'label' => 'Email',                'required' => true],
                    ['id' => 'phone',    'type' => 'phone',    'label' => 'Phone',                'required' => false],
                    ['id' => 'date',     'type' => 'date',     'label' => 'Event Date',           'required' => true],
                    ['id' => 'location', 'type' => 'text',     'label' => 'Event Location',       'required' => false],
                    ['id' => 'guests',   'type' => 'number',   'label' => 'Expected Guest Count', 'min' => 1, 'required' => true],
                    ['id' => 'cuisine',  'type' => 'checkbox', 'label' => 'Cuisine Preferences',
                        'options' => ['Buffet', 'Plated', 'Cocktail / canapes', 'BBQ', 'Dietary-friendly menu', 'Custom'], 'required' => false],
                    ['id' => 'budget',   'type' => 'select',   'label' => 'Budget per head',      'required' => false,
                        'options' => ['< $25', '$25–$50', '$50–$100', '$100+', 'Flexible']],
                    ['id' => 'notes',    'type' => 'textarea', 'label' => 'Additional details',   'rows' => 4, 'required' => false],
                ],
            ],
            'food_feedback' => [
                'label'    => 'Food & Service Feedback',
                'desc'     => 'Post-visit dining review',
                'icon'     => 'fa-star-half-stroke',
                'category' => 'food',
                'fields'   => [
                    ['id' => 'visit_date', 'type' => 'date',     'label' => 'Date of Visit',       'required' => false],
                    ['id' => 'food',       'type' => 'rating',   'label' => 'Food quality',        'max' => 5, 'required' => true],
                    ['id' => 'service',    'type' => 'rating',   'label' => 'Service',             'max' => 5, 'required' => true],
                    ['id' => 'ambience',   'type' => 'rating',   'label' => 'Ambience',            'max' => 5, 'required' => true],
                    ['id' => 'recommend',  'type' => 'radio',    'label' => 'Would you recommend us?',
                        'options' => ['Definitely', 'Probably', 'Probably not', 'Definitely not'], 'required' => true],
                    ['id' => 'comments',   'type' => 'textarea', 'label' => 'Tell us more',        'rows' => 4, 'required' => false],
                    ['id' => 'email',      'type' => 'email',    'label' => 'Email (optional)',    'required' => false],
                ],
            ],
            'private_dining' => [
                'label'    => 'Private Dining Inquiry',
                'desc'     => 'Private room & group bookings',
                'icon'     => 'fa-champagne-glasses',
                'category' => 'food',
                'fields'   => [
                    ['id' => 'name',       'type' => 'text',     'label' => 'Your Name',               'required' => true],
                    ['id' => 'email',      'type' => 'email',    'label' => 'Email',                   'required' => true],
                    ['id' => 'phone',      'type' => 'phone',    'label' => 'Phone',                   'required' => true],
                    ['id' => 'date',       'type' => 'date',     'label' => 'Event Date',              'required' => true],
                    ['id' => 'guests',     'type' => 'number',   'label' => 'Number of Guests',        'min' => 2, 'required' => true],
                    ['id' => 'occasion',   'type' => 'text',     'label' => 'Occasion',               'required' => false],
                    ['id' => 'menu_pref',  'type' => 'radio',    'label' => 'Menu preference',
                        'options' => ['Set menu', 'À la carte', 'Custom / discuss'], 'required' => false],
                    ['id' => 'notes',      'type' => 'textarea', 'label' => 'Special requests',        'rows' => 4, 'required' => false],
                ],
            ],
            'menu_feedback' => [
                'label'    => 'Menu Feedback',
                'desc'     => 'Gather input on new dishes',
                'icon'     => 'fa-clipboard-list',
                'category' => 'food',
                'fields'   => [
                    ['id' => 'tried',       'type' => 'checkbox', 'label' => 'Which dishes did you try?',
                        'options' => ['Starter 1', 'Starter 2', 'Main 1', 'Main 2', 'Dessert 1', 'Dessert 2'], 'required' => false],
                    ['id' => 'favourite',   'type' => 'text',     'label' => 'Your favourite dish', 'required' => false],
                    ['id' => 'improve',     'type' => 'textarea', 'label' => 'What could be improved?', 'rows' => 3, 'required' => false],
                    ['id' => 'suggestions', 'type' => 'textarea', 'label' => 'New dish ideas?',     'rows' => 3, 'required' => false],
                    ['id' => 'rating',      'type' => 'rating',   'label' => 'Overall menu rating', 'max' => 5, 'required' => true],
                ],
            ],

            // ── Health & Wellness ────────────────────────────────────────────
            'appointment_booking' => [
                'label'    => 'Appointment Booking',
                'desc'     => 'Health or wellness consultation',
                'icon'     => 'fa-stethoscope',
                'category' => 'health',
                'fields'   => [
                    ['id' => 'name',        'type' => 'text',     'label' => 'Full Name',              'required' => true],
                    ['id' => 'email',       'type' => 'email',    'label' => 'Email',                  'required' => true],
                    ['id' => 'phone',       'type' => 'phone',    'label' => 'Phone',                  'required' => true],
                    ['id' => 'dob',         'type' => 'date',     'label' => 'Date of Birth',          'required' => false],
                    ['id' => 'service',     'type' => 'select',   'label' => 'Service / Appointment Type', 'required' => true,
                        'options' => ['General consultation', 'Follow-up', 'Assessment', 'Therapy session', 'Other']],
                    ['id' => 'pref_date',   'type' => 'date',     'label' => 'Preferred Date',         'required' => true],
                    ['id' => 'pref_time',   'type' => 'radio',    'label' => 'Preferred Time Slot',
                        'options' => ['Morning (9am–12pm)', 'Afternoon (12pm–5pm)', 'Evening (5pm–8pm)'], 'required' => true],
                    ['id' => 'notes',       'type' => 'textarea', 'label' => 'Reason for visit / symptoms', 'rows' => 4, 'required' => false],
                    ['id' => 'new_patient', 'type' => 'radio',    'label' => 'Are you a new patient?',
                        'options' => ['Yes', 'No'], 'required' => true],
                    ['id' => 'consent',     'type' => 'consent',  'label' => 'I consent to my health information being recorded', 'required' => true],
                ],
            ],
            'fitness_intake' => [
                'label'    => 'Fitness Client Intake',
                'desc'     => 'Goals, history & health screening',
                'icon'     => 'fa-dumbbell',
                'category' => 'health',
                'fields'   => [
                    ['id' => 'name',      'type' => 'text',     'label' => 'Full Name',              'required' => true],
                    ['id' => 'email',     'type' => 'email',    'label' => 'Email',                  'required' => true],
                    ['id' => 'phone',     'type' => 'phone',    'label' => 'Phone',                  'required' => false],
                    ['id' => 'age',       'type' => 'number',   'label' => 'Age',                    'required' => false],
                    ['id' => 'goals',     'type' => 'checkbox', 'label' => 'Fitness Goals',
                        'options' => ['Weight loss', 'Muscle gain', 'Endurance / cardio', 'Flexibility', 'Stress relief', 'General fitness'], 'required' => true],
                    ['id' => 'injuries',  'type' => 'textarea', 'label' => 'Any injuries or medical conditions we should know?', 'rows' => 3, 'required' => false],
                    ['id' => 'activity',  'type' => 'select',   'label' => 'Current activity level', 'required' => true,
                        'options' => ['Sedentary', 'Lightly active', 'Moderately active', 'Very active']],
                    ['id' => 'sessions',  'type' => 'radio',    'label' => 'Preferred sessions per week',
                        'options' => ['1–2', '3–4', '5+'], 'required' => false],
                    ['id' => 'consent',   'type' => 'consent',  'label' => 'I confirm I am medically cleared to participate in physical training', 'required' => true],
                ],
            ],
            'nutrition_consultation' => [
                'label'    => 'Nutrition Consultation',
                'desc'     => 'Diet history & health goals',
                'icon'     => 'fa-apple-whole',
                'category' => 'health',
                'fields'   => [
                    ['id' => 'name',       'type' => 'text',     'label' => 'Full Name',          'required' => true],
                    ['id' => 'email',      'type' => 'email',    'label' => 'Email',              'required' => true],
                    ['id' => 'goal',       'type' => 'checkbox', 'label' => 'Health goals',
                        'options' => ['Weight management', 'Improve energy', 'Manage a medical condition', 'Sports performance', 'Gut health', 'Other'], 'required' => true],
                    ['id' => 'diet',       'type' => 'radio',    'label' => 'Current diet',
                        'options' => ['Omnivore', 'Vegetarian', 'Vegan', 'Keto / low-carb', 'Other'], 'required' => false],
                    ['id' => 'allergies',  'type' => 'text',     'label' => 'Food allergies / intolerances', 'required' => false],
                    ['id' => 'conditions', 'type' => 'textarea', 'label' => 'Medical conditions or medications', 'rows' => 3, 'required' => false],
                    ['id' => 'notes',      'type' => 'textarea', 'label' => 'Anything else you\'d like to share?', 'rows' => 3, 'required' => false],
                    ['id' => 'consent',    'type' => 'consent',  'label' => 'I understand this is not a substitute for medical advice', 'required' => true],
                ],
            ],
            'mental_health_intake' => [
                'label'    => 'Therapy Intake Form',
                'desc'     => 'New client intake for therapists',
                'icon'     => 'fa-brain',
                'category' => 'health',
                'fields'   => [
                    ['id' => 'name',     'type' => 'text',     'label' => 'Full Name',              'required' => true],
                    ['id' => 'email',    'type' => 'email',    'label' => 'Email',                  'required' => true],
                    ['id' => 'phone',    'type' => 'phone',    'label' => 'Phone',                  'required' => true],
                    ['id' => 'dob',      'type' => 'date',     'label' => 'Date of Birth',          'required' => false],
                    ['id' => 'referral', 'type' => 'select',   'label' => 'How did you hear about us?', 'required' => false,
                        'options' => ['Online search', 'Referral from doctor', 'Friend / family', 'Insurance directory', 'Social media', 'Other']],
                    ['id' => 'concerns', 'type' => 'checkbox', 'label' => 'Primary concerns',
                        'options' => ['Anxiety', 'Depression', 'Stress / burnout', 'Relationships', 'Grief / loss', 'Trauma', 'Other'], 'required' => false],
                    ['id' => 'goals',    'type' => 'textarea', 'label' => 'What are you hoping to achieve through therapy?', 'rows' => 4, 'required' => false],
                    ['id' => 'consent',  'type' => 'consent',  'label' => 'I consent to the collection and use of my information for therapeutic purposes', 'required' => true],
                ],
            ],
            'yoga_class_signup' => [
                'label'    => 'Yoga Class Sign-up',
                'desc'     => 'Level + class preferences',
                'icon'     => 'fa-person-praying',
                'category' => 'health',
                'fields'   => [
                    ['id' => 'name',     'type' => 'text',     'label' => 'Full Name',          'required' => true],
                    ['id' => 'email',    'type' => 'email',    'label' => 'Email',              'required' => true],
                    ['id' => 'phone',    'type' => 'phone',    'label' => 'Phone',              'required' => false],
                    ['id' => 'level',    'type' => 'radio',    'label' => 'Experience Level',
                        'options' => ['Complete beginner', 'Some experience', 'Intermediate', 'Advanced'], 'required' => true],
                    ['id' => 'style',    'type' => 'checkbox', 'label' => 'Preferred styles',
                        'options' => ['Hatha', 'Vinyasa', 'Yin', 'Restorative', 'Power', 'Hot yoga', 'Open to any'], 'required' => false],
                    ['id' => 'injuries', 'type' => 'textarea', 'label' => 'Any injuries or conditions the instructor should know?', 'rows' => 3, 'required' => false],
                    ['id' => 'consent',  'type' => 'consent',  'label' => 'I confirm I am in suitable health to participate in yoga', 'required' => true],
                ],
            ],

            // ── Education ───────────────────────────────────────────────────
            'course_enrollment' => [
                'label'    => 'Course Enrollment',
                'desc'     => 'Student registration & payment',
                'icon'     => 'fa-graduation-cap',
                'category' => 'education',
                'fields'   => [
                    ['id' => 'student_name', 'type' => 'text',     'label' => 'Student Full Name',   'required' => true],
                    ['id' => 'email',        'type' => 'email',    'label' => 'Email',               'required' => true],
                    ['id' => 'phone',        'type' => 'phone',    'label' => 'Phone',               'required' => false],
                    ['id' => 'course',       'type' => 'select',   'label' => 'Select Course',       'required' => true,
                        'options' => ['Course A', 'Course B', 'Course C', 'Course D']],
                    ['id' => 'start',        'type' => 'date',     'label' => 'Preferred Start Date','required' => false],
                    ['id' => 'experience',   'type' => 'textarea', 'label' => 'Relevant background / experience', 'rows' => 3, 'required' => false],
                    ['id' => 'consent',      'type' => 'consent',  'label' => 'I agree to the enrollment terms', 'required' => true],
                ],
            ],
            'scholarship_application' => [
                'label'    => 'Scholarship Application',
                'desc'     => 'Academic + financial details',
                'icon'     => 'fa-trophy',
                'category' => 'education',
                'fields'   => [
                    ['id' => 'name',          'type' => 'text',     'label' => 'Full Name',              'required' => true],
                    ['id' => 'email',         'type' => 'email',    'label' => 'Email',                  'required' => true],
                    ['id' => 'school',        'type' => 'text',     'label' => 'Current School / Institution', 'required' => true],
                    ['id' => 'program',       'type' => 'text',     'label' => 'Program / Field of Study','required' => true],
                    ['id' => 'gpa',           'type' => 'text',     'label' => 'Current GPA / Grade',    'required' => false],
                    ['id' => 'essay',         'type' => 'textarea', 'label' => 'Personal statement (500 words max)', 'rows' => 10, 'required' => true],
                    ['id' => 'documents',     'type' => 'file',     'label' => 'Upload supporting documents (transcript, ID)', 'required' => false],
                    ['id' => 'consent',       'type' => 'consent',  'label' => 'I certify that all information provided is accurate', 'required' => true],
                ],
            ],
            'student_feedback' => [
                'label'    => 'Student Course Feedback',
                'desc'     => 'End-of-term evaluation',
                'icon'     => 'fa-comments',
                'category' => 'education',
                'fields'   => [
                    ['id' => 'course',    'type' => 'text',     'label' => 'Course Name',            'required' => false],
                    ['id' => 'content',   'type' => 'rating',   'label' => 'Course content quality', 'max' => 5, 'required' => true],
                    ['id' => 'delivery',  'type' => 'rating',   'label' => 'Instructor / delivery',  'max' => 5, 'required' => true],
                    ['id' => 'materials', 'type' => 'rating',   'label' => 'Course materials',       'max' => 5, 'required' => true],
                    ['id' => 'pace',      'type' => 'radio',    'label' => 'Course pace',
                        'options' => ['Too slow', 'Just right', 'Too fast'], 'required' => true],
                    ['id' => 'recommend', 'type' => 'scale',    'label' => 'Would you recommend this course?', 'min' => 0, 'max' => 10, 'required' => true],
                    ['id' => 'comments',  'type' => 'textarea', 'label' => 'Additional comments',   'rows' => 4, 'required' => false],
                ],
            ],
            'tutoring_request' => [
                'label'    => 'Tutoring Request',
                'desc'     => 'Subject + availability matching',
                'icon'     => 'fa-book-open',
                'category' => 'education',
                'fields'   => [
                    ['id' => 'name',        'type' => 'text',     'label' => 'Student / Parent Name', 'required' => true],
                    ['id' => 'email',       'type' => 'email',    'label' => 'Email',                 'required' => true],
                    ['id' => 'phone',       'type' => 'phone',    'label' => 'Phone',                 'required' => false],
                    ['id' => 'subject',     'type' => 'select',   'label' => 'Subject',               'required' => true,
                        'options' => ['Mathematics', 'English', 'Science', 'History', 'Languages', 'Computer Science', 'Test prep', 'Other']],
                    ['id' => 'grade',       'type' => 'text',     'label' => 'Year / Grade level',    'required' => false],
                    ['id' => 'frequency',   'type' => 'radio',    'label' => 'Sessions per week',
                        'options' => ['Once', 'Twice', '3+ times'], 'required' => false],
                    ['id' => 'format',      'type' => 'radio',    'label' => 'Preferred format',
                        'options' => ['In-person', 'Online', 'Either'], 'required' => true],
                    ['id' => 'goals',       'type' => 'textarea', 'label' => 'What do you want to achieve?', 'rows' => 3, 'required' => false],
                ],
            ],
            'parent_teacher_meeting' => [
                'label'    => 'Parent-Teacher Meeting',
                'desc'     => 'Schedule a parent-teacher conference',
                'icon'     => 'fa-school',
                'category' => 'education',
                'fields'   => [
                    ['id' => 'parent_name', 'type' => 'text',     'label' => 'Parent / Guardian Name','required' => true],
                    ['id' => 'email',       'type' => 'email',    'label' => 'Email',                 'required' => true],
                    ['id' => 'phone',       'type' => 'phone',    'label' => 'Phone',                 'required' => false],
                    ['id' => 'student',     'type' => 'text',     'label' => 'Student Name',          'required' => true],
                    ['id' => 'class',       'type' => 'text',     'label' => 'Class / Year group',    'required' => false],
                    ['id' => 'date',        'type' => 'date',     'label' => 'Preferred Date',        'required' => true],
                    ['id' => 'time',        'type' => 'time',     'label' => 'Preferred Time',        'required' => false],
                    ['id' => 'topics',      'type' => 'textarea', 'label' => 'Topics you would like to discuss', 'rows' => 3, 'required' => false],
                ],
            ],

            // ── Real Estate ──────────────────────────────────────────────────
            'property_inquiry' => [
                'label'    => 'Property Inquiry',
                'desc'     => 'Buyer / renter enquiry',
                'icon'     => 'fa-house',
                'category' => 'real_estate',
                'fields'   => [
                    ['id' => 'name',       'type' => 'text',     'label' => 'Full Name',          'required' => true],
                    ['id' => 'email',      'type' => 'email',    'label' => 'Email',              'required' => true],
                    ['id' => 'phone',      'type' => 'phone',    'label' => 'Phone',              'required' => false],
                    ['id' => 'intent',     'type' => 'radio',    'label' => 'Are you looking to…',
                        'options' => ['Buy', 'Rent', 'Invest'], 'required' => true],
                    ['id' => 'bedrooms',   'type' => 'select',   'label' => 'Bedrooms',           'required' => false,
                        'options' => ['Studio', '1', '2', '3', '4', '5+']],
                    ['id' => 'budget',     'type' => 'text',     'label' => 'Budget / Price range','required' => false],
                    ['id' => 'location',   'type' => 'text',     'label' => 'Preferred area / suburb', 'required' => false],
                    ['id' => 'timeline',   'type' => 'select',   'label' => 'Timeline',           'required' => false,
                        'options' => ['Immediately', 'Within 1 month', '1–3 months', '3–6 months', 'Just browsing']],
                    ['id' => 'notes',      'type' => 'textarea', 'label' => 'Additional requirements', 'rows' => 3, 'required' => false],
                ],
            ],
            'property_valuation' => [
                'label'    => 'Property Valuation Request',
                'desc'     => 'Free home appraisal request',
                'icon'     => 'fa-magnifying-glass-dollar',
                'category' => 'real_estate',
                'fields'   => [
                    ['id' => 'name',       'type' => 'text',     'label' => 'Full Name',          'required' => true],
                    ['id' => 'email',      'type' => 'email',    'label' => 'Email',              'required' => true],
                    ['id' => 'phone',      'type' => 'phone',    'label' => 'Phone',              'required' => true],
                    ['id' => 'address',    'type' => 'text',     'label' => 'Property Address',   'required' => true],
                    ['id' => 'type',       'type' => 'select',   'label' => 'Property Type',      'required' => true,
                        'options' => ['House', 'Apartment / Unit', 'Townhouse', 'Villa', 'Land', 'Commercial']],
                    ['id' => 'bedrooms',   'type' => 'select',   'label' => 'Bedrooms',           'required' => false,
                        'options' => ['Studio', '1', '2', '3', '4', '5+']],
                    ['id' => 'reason',     'type' => 'radio',    'label' => 'Reason for valuation',
                        'options' => ['Planning to sell', 'Refinancing', 'Insurance', 'Curiosity'], 'required' => false],
                    ['id' => 'notes',      'type' => 'textarea', 'label' => 'Additional details about the property', 'rows' => 3, 'required' => false],
                ],
            ],
            'open_home_registration' => [
                'label'    => 'Open Home Registration',
                'desc'     => 'Visitor sign-in for open inspections',
                'icon'     => 'fa-door-open',
                'category' => 'real_estate',
                'fields'   => [
                    ['id' => 'name',      'type' => 'text',    'label' => 'Full Name',           'required' => true],
                    ['id' => 'email',     'type' => 'email',   'label' => 'Email',               'required' => true],
                    ['id' => 'phone',     'type' => 'phone',   'label' => 'Phone',               'required' => false],
                    ['id' => 'intent',    'type' => 'radio',   'label' => 'Buying or renting?',
                        'options' => ['Buying', 'Renting', 'Investing', 'Just looking'], 'required' => true],
                    ['id' => 'finance',   'type' => 'radio',   'label' => 'Finance / pre-approval?',
                        'options' => ['Yes, pre-approved', 'In progress', 'Not yet', 'Cash buyer'], 'required' => false],
                    ['id' => 'timeline',  'type' => 'select',  'label' => 'Purchase timeline',   'required' => false,
                        'options' => ['Immediately', '1–3 months', '3–6 months', '6+ months', 'Undecided']],
                    ['id' => 'questions', 'type' => 'textarea','label' => 'Questions for the agent', 'rows' => 3, 'required' => false],
                ],
            ],
            'rental_application' => [
                'label'    => 'Rental Application',
                'desc'     => 'Tenant application form',
                'icon'     => 'fa-file-signature',
                'category' => 'real_estate',
                'fields'   => [
                    ['id' => 'name',       'type' => 'text',    'label' => 'Full Name',           'required' => true],
                    ['id' => 'email',      'type' => 'email',   'label' => 'Email',               'required' => true],
                    ['id' => 'phone',      'type' => 'phone',   'label' => 'Phone',               'required' => true],
                    ['id' => 'dob',        'type' => 'date',    'label' => 'Date of Birth',       'required' => false],
                    ['id' => 'employment', 'type' => 'radio',   'label' => 'Employment status',
                        'options' => ['Full-time', 'Part-time', 'Self-employed', 'Retired', 'Student', 'Other'], 'required' => true],
                    ['id' => 'income',     'type' => 'text',    'label' => 'Annual income (approximate)', 'required' => false],
                    ['id' => 'move_in',    'type' => 'date',    'label' => 'Desired move-in date','required' => true],
                    ['id' => 'occupants',  'type' => 'number',  'label' => 'Number of occupants','min' => 1, 'required' => true],
                    ['id' => 'pets',       'type' => 'radio',   'label' => 'Do you have pets?',
                        'options' => ['No', 'Yes — please describe below'], 'required' => true],
                    ['id' => 'documents',  'type' => 'file',    'label' => 'Supporting documents (ID, payslips)', 'required' => false],
                    ['id' => 'consent',    'type' => 'consent', 'label' => 'I confirm all information is true and accurate', 'required' => true],
                ],
            ],

            // ── Nonprofit & Community ────────────────────────────────────────
            'donation_pledge' => [
                'label'    => 'Donation Pledge',
                'desc'     => 'Supporter commitment form',
                'icon'     => 'fa-hand-holding-heart',
                'category' => 'nonprofit',
                'fields'   => [
                    ['id' => 'name',      'type' => 'text',     'label' => 'Full Name',            'required' => true],
                    ['id' => 'email',     'type' => 'email',    'label' => 'Email',                'required' => true],
                    ['id' => 'frequency', 'type' => 'radio',    'label' => 'Donation frequency',
                        'options' => ['One-time', 'Monthly', 'Quarterly', 'Annually'], 'required' => true],
                    ['id' => 'amount',    'type' => 'select',   'label' => 'Amount',               'required' => false,
                        'options' => ['$10', '$25', '$50', '$100', '$250', '$500', 'Custom amount']],
                    ['id' => 'fund',      'type' => 'select',   'label' => 'Designate your gift',  'required' => false,
                        'options' => ['Where most needed', 'Program A', 'Program B', 'Emergency fund']],
                    ['id' => 'anonymous', 'type' => 'radio',    'label' => 'List me as anonymous?',
                        'options' => ['No, include my name', 'Yes, keep me anonymous'], 'required' => false],
                    ['id' => 'message',   'type' => 'textarea', 'label' => 'Message of support (optional)', 'rows' => 3, 'required' => false],
                    ['id' => 'consent',   'type' => 'consent',  'label' => 'I agree to the privacy policy and gift processing terms', 'required' => true],
                ],
            ],
            'membership_application' => [
                'label'    => 'Membership Application',
                'desc'     => 'Club / community membership',
                'icon'     => 'fa-id-badge',
                'category' => 'nonprofit',
                'fields'   => [
                    ['id' => 'name',       'type' => 'text',     'label' => 'Full Name',           'required' => true],
                    ['id' => 'email',      'type' => 'email',    'label' => 'Email',               'required' => true],
                    ['id' => 'phone',      'type' => 'phone',    'label' => 'Phone',               'required' => false],
                    ['id' => 'address',    'type' => 'text',     'label' => 'Address',             'required' => false],
                    ['id' => 'tier',       'type' => 'radio',    'label' => 'Membership tier',
                        'options' => ['Standard', 'Family', 'Senior / Concession', 'Student', 'Lifetime'], 'required' => true],
                    ['id' => 'interests',  'type' => 'checkbox', 'label' => 'Areas of interest',
                        'options' => ['Volunteering', 'Events', 'Committees', 'Fundraising', 'Communications'], 'required' => false],
                    ['id' => 'referral',   'type' => 'text',     'label' => 'How did you hear about us?', 'required' => false],
                    ['id' => 'consent',    'type' => 'consent',  'label' => 'I agree to the membership terms and code of conduct', 'required' => true],
                ],
            ],
            'grant_application' => [
                'label'    => 'Grant Application',
                'desc'     => 'Community grant / funding request',
                'icon'     => 'fa-money-bill-wave',
                'category' => 'nonprofit',
                'fields'   => [
                    ['id' => 'org_name',    'type' => 'text',     'label' => 'Organisation Name',    'required' => true],
                    ['id' => 'contact',     'type' => 'text',     'label' => 'Contact Person',       'required' => true],
                    ['id' => 'email',       'type' => 'email',    'label' => 'Email',                'required' => true],
                    ['id' => 'abn',         'type' => 'text',     'label' => 'Registration / Tax ID','required' => false],
                    ['id' => 'project',     'type' => 'text',     'label' => 'Project Name',         'required' => true],
                    ['id' => 'amount',      'type' => 'number',   'label' => 'Amount Requested ($)', 'min' => 0, 'required' => true],
                    ['id' => 'description', 'type' => 'textarea', 'label' => 'Project description and community impact', 'rows' => 6, 'required' => true],
                    ['id' => 'timeline',    'type' => 'text',     'label' => 'Project timeline',     'required' => false],
                    ['id' => 'documents',   'type' => 'file',     'label' => 'Supporting documents', 'required' => false],
                    ['id' => 'consent',     'type' => 'consent',  'label' => 'I certify all information provided is accurate', 'required' => true],
                ],
            ],
            'petition' => [
                'label'    => 'Online Petition',
                'desc'     => 'Collect supporter signatures',
                'icon'     => 'fa-pen-to-square',
                'category' => 'nonprofit',
                'fields'   => [
                    ['id' => 'name',      'type' => 'text',      'label' => 'Full Name',          'required' => true],
                    ['id' => 'email',     'type' => 'email',     'label' => 'Email',              'required' => true],
                    ['id' => 'location',  'type' => 'text',      'label' => 'City / Country',     'required' => false],
                    ['id' => 'comment',   'type' => 'textarea',  'label' => 'Why do you support this? (optional)', 'rows' => 3, 'required' => false],
                    ['id' => 'public',    'type' => 'radio',     'label' => 'Show my name publicly?',
                        'options' => ['Yes', 'No — keep private'], 'required' => true],
                    ['id' => 'updates',   'type' => 'radio',     'label' => 'Receive campaign updates?',
                        'options' => ['Yes, email me', 'No thanks'], 'required' => false],
                    ['id' => 'consent',   'type' => 'consent',   'label' => 'I agree to the terms of this petition', 'required' => true],
                ],
            ],
            'community_interest' => [
                'label'    => 'Community Interest Form',
                'desc'     => 'Gauge interest in a new initiative',
                'icon'     => 'fa-people-group',
                'category' => 'nonprofit',
                'fields'   => [
                    ['id' => 'name',     'type' => 'text',     'label' => 'Your Name',              'required' => false],
                    ['id' => 'email',    'type' => 'email',    'label' => 'Email',                  'required' => true],
                    ['id' => 'interest', 'type' => 'rating',   'label' => 'How interested are you?','max' => 5, 'required' => true],
                    ['id' => 'involve',  'type' => 'checkbox', 'label' => 'How would you like to be involved?',
                        'options' => ['As a participant', 'As a volunteer', 'As a sponsor / donor', 'Spread the word', 'Other'], 'required' => false],
                    ['id' => 'feedback', 'type' => 'textarea', 'label' => 'Any ideas or feedback?', 'rows' => 4, 'required' => false],
                ],
            ],

            // ── Freelancers & Services ───────────────────────────────────────
            'project_brief' => [
                'label'    => 'Project Brief',
                'desc'     => 'Client brief for freelance work',
                'icon'     => 'fa-user-tie',
                'category' => 'freelance',
                'fields'   => [
                    ['id' => 'client',    'type' => 'text',     'label' => 'Your Name / Company',   'required' => true],
                    ['id' => 'email',     'type' => 'email',    'label' => 'Email',                 'required' => true],
                    ['id' => 'website',   'type' => 'url',      'label' => 'Website / Portfolio (optional)', 'required' => false],
                    ['id' => 'service',   'type' => 'checkbox', 'label' => 'Services needed',
                        'options' => ['Design', 'Development', 'Copywriting', 'Photography', 'Video', 'Marketing', 'Other'], 'required' => true],
                    ['id' => 'budget',    'type' => 'select',   'label' => 'Budget range',          'required' => false,
                        'options' => ['< $500', '$500–$2k', '$2k–$10k', '$10k–$50k', '$50k+', 'Discuss']],
                    ['id' => 'deadline',  'type' => 'date',     'label' => 'Deadline',              'required' => false],
                    ['id' => 'brief',     'type' => 'textarea', 'label' => 'Project brief / description', 'required' => true, 'rows' => 6],
                    ['id' => 'files',     'type' => 'file',     'label' => 'Attach reference files', 'required' => false],
                ],
            ],
            'quote_request' => [
                'label'    => 'Quote Request',
                'desc'     => 'Request a service quote',
                'icon'     => 'fa-file-invoice-dollar',
                'category' => 'freelance',
                'fields'   => [
                    ['id' => 'name',     'type' => 'text',     'label' => 'Full Name',             'required' => true],
                    ['id' => 'email',    'type' => 'email',    'label' => 'Email',                 'required' => true],
                    ['id' => 'phone',    'type' => 'phone',    'label' => 'Phone',                 'required' => false],
                    ['id' => 'service',  'type' => 'select',   'label' => 'Service required',      'required' => true,
                        'options' => ['Service A', 'Service B', 'Service C', 'Other / not sure']],
                    ['id' => 'details',  'type' => 'textarea', 'label' => 'Describe what you need','required' => true, 'rows' => 5],
                    ['id' => 'timeline', 'type' => 'select',   'label' => 'Timeline',              'required' => false,
                        'options' => ['ASAP', 'Within a week', '2–4 weeks', '1–3 months', 'Flexible']],
                    ['id' => 'files',    'type' => 'file',     'label' => 'Attach reference files', 'required' => false],
                ],
            ],
            'testimonial_request' => [
                'label'    => 'Testimonial Collection',
                'desc'     => 'Gather client reviews',
                'icon'     => 'fa-quote-right',
                'category' => 'freelance',
                'fields'   => [
                    ['id' => 'name',        'type' => 'text',     'label' => 'Your Name',           'required' => true],
                    ['id' => 'company',     'type' => 'text',     'label' => 'Company / Role',      'required' => false],
                    ['id' => 'rating',      'type' => 'rating',   'label' => 'Overall experience',  'max' => 5, 'required' => true],
                    ['id' => 'testimonial', 'type' => 'textarea', 'label' => 'Your testimonial',    'required' => true, 'rows' => 5],
                    ['id' => 'use_quote',   'type' => 'radio',    'label' => 'Can we use this publicly?',
                        'options' => ['Yes, with my name', 'Yes, anonymously', 'No'], 'required' => true],
                    ['id' => 'photo',       'type' => 'file',     'label' => 'Upload your photo (optional)', 'required' => false],
                ],
            ],
            'discovery_call' => [
                'label'    => 'Discovery Call',
                'desc'     => 'Pre-call questionnaire',
                'icon'     => 'fa-phone-volume',
                'category' => 'freelance',
                'fields'   => [
                    ['id' => 'name',       'type' => 'text',     'label' => 'Your Name',              'required' => true],
                    ['id' => 'email',      'type' => 'email',    'label' => 'Email',                  'required' => true],
                    ['id' => 'company',    'type' => 'text',     'label' => 'Company / Brand',        'required' => false],
                    ['id' => 'challenge',  'type' => 'textarea', 'label' => 'What challenge are you trying to solve?', 'rows' => 4, 'required' => true],
                    ['id' => 'tried',      'type' => 'textarea', 'label' => 'What have you already tried?', 'rows' => 3, 'required' => false],
                    ['id' => 'budget',     'type' => 'select',   'label' => 'Budget in mind?',        'required' => false,
                        'options' => ['Not sure yet', '< $1k', '$1k–$5k', '$5k–$20k', '$20k+']],
                    ['id' => 'timeline',   'type' => 'select',   'label' => 'Timeline / urgency',     'required' => false,
                        'options' => ['ASAP', '1–4 weeks', '1–3 months', 'No rush']],
                    ['id' => 'date',       'type' => 'date',     'label' => 'Preferred call date',    'required' => false],
                ],
            ],
            'onboarding_checklist' => [
                'label'    => 'Client Onboarding',
                'desc'     => 'New client welcome & info collection',
                'icon'     => 'fa-clipboard-check',
                'category' => 'freelance',
                'fields'   => [
                    ['id' => 'name',       'type' => 'text',    'label' => 'Your Name',               'required' => true],
                    ['id' => 'company',    'type' => 'text',    'label' => 'Company',                  'required' => false],
                    ['id' => 'email',      'type' => 'email',   'label' => 'Email',                    'required' => true],
                    ['id' => 'website',    'type' => 'url',     'label' => 'Website',                  'required' => false],
                    ['id' => 'goals',      'type' => 'textarea','label' => 'What are your main goals?', 'rows' => 4, 'required' => true],
                    ['id' => 'audience',   'type' => 'textarea','label' => 'Who is your target audience?', 'rows' => 3, 'required' => false],
                    ['id' => 'brand_doc',  'type' => 'file',    'label' => 'Upload brand guide / assets', 'required' => false],
                    ['id' => 'deadline',   'type' => 'date',    'label' => 'Project deadline',         'required' => false],
                    ['id' => 'comms',      'type' => 'radio',   'label' => 'Preferred communication',
                        'options' => ['Email', 'Slack', 'WhatsApp', 'Video calls'], 'required' => false],
                ],
            ],
            'photography_booking' => [
                'label'    => 'Photography Booking',
                'desc'     => 'Session type, date & location',
                'icon'     => 'fa-camera',
                'category' => 'freelance',
                'fields'   => [
                    ['id' => 'name',     'type' => 'text',     'label' => 'Your Name',              'required' => true],
                    ['id' => 'email',    'type' => 'email',    'label' => 'Email',                  'required' => true],
                    ['id' => 'phone',    'type' => 'phone',    'label' => 'Phone',                  'required' => false],
                    ['id' => 'type',     'type' => 'select',   'label' => 'Session Type',           'required' => true,
                        'options' => ['Portrait', 'Wedding', 'Corporate / headshots', 'Product / commercial', 'Event', 'Family', 'Other']],
                    ['id' => 'date',     'type' => 'date',     'label' => 'Preferred Date',         'required' => true],
                    ['id' => 'location', 'type' => 'text',     'label' => 'Preferred Location',     'required' => false],
                    ['id' => 'duration', 'type' => 'select',   'label' => 'Session Duration',       'required' => false,
                        'options' => ['30 minutes', '1 hour', '2 hours', '3+ hours', 'Full day']],
                    ['id' => 'notes',    'type' => 'textarea', 'label' => 'Vision / notes',         'rows' => 4, 'required' => false],
                ],
            ],

            // ── Retail & E-commerce ──────────────────────────────────────────
            'product_preorder' => [
                'label'    => 'Product Pre-order',
                'desc'     => 'Capture interest before launch',
                'icon'     => 'fa-bag-shopping',
                'category' => 'retail',
                'fields'   => [
                    ['id' => 'name',      'type' => 'text',     'label' => 'Full Name',              'required' => true],
                    ['id' => 'email',     'type' => 'email',    'label' => 'Email',                  'required' => true],
                    ['id' => 'product',   'type' => 'select',   'label' => 'Product / Variant',      'required' => true,
                        'options' => ['Option A', 'Option B', 'Option C']],
                    ['id' => 'quantity',  'type' => 'number',   'label' => 'Quantity',               'min' => 1, 'required' => true],
                    ['id' => 'notify',    'type' => 'radio',    'label' => 'How should we notify you?',
                        'options' => ['Email only', 'SMS only', 'Both email & SMS'], 'required' => false],
                    ['id' => 'notes',     'type' => 'textarea', 'label' => 'Special requests or notes', 'rows' => 3, 'required' => false],
                    ['id' => 'consent',   'type' => 'consent',  'label' => 'I agree to be notified when the product is available', 'required' => true],
                ],
            ],
            'return_request' => [
                'label'    => 'Return / Refund Request',
                'desc'     => 'Structured returns process',
                'icon'     => 'fa-arrow-rotate-left',
                'category' => 'retail',
                'fields'   => [
                    ['id' => 'name',       'type' => 'text',     'label' => 'Full Name',             'required' => true],
                    ['id' => 'email',      'type' => 'email',    'label' => 'Email',                 'required' => true],
                    ['id' => 'order_id',   'type' => 'text',     'label' => 'Order Number',          'required' => true],
                    ['id' => 'product',    'type' => 'text',     'label' => 'Product Name / SKU',    'required' => true],
                    ['id' => 'reason',     'type' => 'select',   'label' => 'Return Reason',         'required' => true,
                        'options' => ['Defective / damaged', 'Wrong item', 'Not as described', 'Changed my mind', 'Other']],
                    ['id' => 'resolution', 'type' => 'radio',    'label' => 'Preferred resolution',
                        'options' => ['Full refund', 'Exchange / replacement', 'Store credit'], 'required' => true],
                    ['id' => 'photo',      'type' => 'file',     'label' => 'Photo of item (if damaged)', 'required' => false],
                    ['id' => 'notes',      'type' => 'textarea', 'label' => 'Additional details',    'rows' => 3, 'required' => false],
                ],
            ],
            'size_fit_quiz' => [
                'label'    => 'Size & Fit Quiz',
                'desc'     => 'Help customers find the right size',
                'icon'     => 'fa-ruler',
                'category' => 'retail',
                'fields'   => [
                    ['id' => 'name',      'type' => 'text',    'label' => 'Your Name',               'required' => false],
                    ['id' => 'email',     'type' => 'email',   'label' => 'Email (to receive result)','required' => false],
                    ['id' => 'gender',    'type' => 'radio',   'label' => 'Shopping for',
                        'options' => ['Women', 'Men', 'Kids', 'Unisex'], 'required' => true],
                    ['id' => 'height',    'type' => 'text',    'label' => 'Height (cm or ft/in)',     'required' => false],
                    ['id' => 'weight',    'type' => 'text',    'label' => 'Weight (kg or lbs)',       'required' => false],
                    ['id' => 'chest',     'type' => 'text',    'label' => 'Chest / bust measurement', 'required' => false],
                    ['id' => 'waist',     'type' => 'text',    'label' => 'Waist measurement',        'required' => false],
                    ['id' => 'fit_pref',  'type' => 'radio',   'label' => 'Preferred fit',
                        'options' => ['Slim / tailored', 'Regular', 'Relaxed / oversized'], 'required' => false],
                ],
            ],
            'waitlist_signup' => [
                'label'    => 'Waitlist Sign-up',
                'desc'     => 'Join the queue for sold-out items',
                'icon'     => 'fa-clock',
                'category' => 'retail',
                'fields'   => [
                    ['id' => 'name',    'type' => 'text',    'label' => 'Your Name',          'required' => true],
                    ['id' => 'email',   'type' => 'email',   'label' => 'Email',              'required' => true],
                    ['id' => 'phone',   'type' => 'phone',   'label' => 'Phone (optional)',   'required' => false],
                    ['id' => 'product', 'type' => 'text',    'label' => 'Product / size you want', 'required' => true],
                    ['id' => 'notify',  'type' => 'radio',   'label' => 'Notify me via',
                        'options' => ['Email', 'SMS', 'Both'], 'required' => true],
                    ['id' => 'consent', 'type' => 'consent', 'label' => 'I agree to be contacted when this item is back in stock', 'required' => true],
                ],
            ],
            'wholesale_inquiry' => [
                'label'    => 'Wholesale Inquiry',
                'desc'     => 'B2B / stockist applications',
                'icon'     => 'fa-boxes-stacked',
                'category' => 'retail',
                'fields'   => [
                    ['id' => 'business',    'type' => 'text',    'label' => 'Business Name',        'required' => true],
                    ['id' => 'name',        'type' => 'text',    'label' => 'Contact Name',         'required' => true],
                    ['id' => 'email',       'type' => 'email',   'label' => 'Email',                'required' => true],
                    ['id' => 'phone',       'type' => 'phone',   'label' => 'Phone',                'required' => false],
                    ['id' => 'website',     'type' => 'url',     'label' => 'Website',              'required' => false],
                    ['id' => 'location',    'type' => 'text',    'label' => 'Store / warehouse location', 'required' => false],
                    ['id' => 'products',    'type' => 'textarea','label' => 'Products you\'re interested in', 'rows' => 3, 'required' => true],
                    ['id' => 'volume',      'type' => 'select',  'label' => 'Estimated monthly order volume', 'required' => false,
                        'options' => ['< 50 units', '50–200 units', '200–1000 units', '1000+ units', 'Not sure yet']],
                    ['id' => 'consent',     'type' => 'consent', 'label' => 'I understand wholesale pricing requires approval', 'required' => true],
                ],
            ],
        ];
    }

    /**
     * Templates grouped by category, in category display order.
     *
     * @return array<string, array<string, array{label:string,desc:string,icon:string,category:string,fields:array}>>
     */
    public static function grouped(): array
    {
        $byCategory = [];
        foreach (self::all() as $key => $tpl) {
            $byCategory[$tpl['category']][$key] = $tpl;
        }

        $ordered = [];
        foreach (array_keys(self::categories()) as $cat) {
            if (isset($byCategory[$cat])) {
                $ordered[$cat] = $byCategory[$cat];
            }
        }

        // Defensive: any category present in templates but missing from categories() list
        foreach ($byCategory as $cat => $templates) {
            if (!isset($ordered[$cat])) {
                $ordered[$cat] = $templates;
            }
        }

        return $ordered;
    }

    /** All valid template keys. @return list<string> */
    public static function keys(): array
    {
        return array_keys(self::all());
    }

    public static function isValid(?string $key): bool
    {
        return $key !== null && isset(self::all()[$key]);
    }

    public static function fieldsFor(string $key): array
    {
        return self::all()[$key]['fields'] ?? [];
    }

    public static function categoryLabel(string $cat): string
    {
        return self::categories()[$cat]['label'] ?? ucfirst($cat);
    }

    public static function categoryIcon(string $cat): string
    {
        return self::categories()[$cat]['icon'] ?? 'fa-layer-group';
    }
}
