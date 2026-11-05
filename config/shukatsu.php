<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Login allowlist
    |--------------------------------------------------------------------------
    |
    | This app is single-user. Only Google accounts whose verified email is
    | listed here may sign in. Comma separated in ALLOWED_LOGIN_EMAILS.
    |
    */

    'allowed_emails' => array_values(array_filter(array_map(
        fn (string $email) => strtolower(trim($email)),
        explode(',', (string) env('ALLOWED_LOGIN_EMAILS', '')),
    ))),

    /*
    |--------------------------------------------------------------------------
    | Local development login
    |--------------------------------------------------------------------------
    |
    | Lets you sign in without Google while developing locally. It is only
    | honoured when APP_ENV=local, so it can never be enabled in production.
    |
    */

    'dev_login' => (bool) env('DEV_LOGIN_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | Google API scopes
    |--------------------------------------------------------------------------
    */

    'google_scopes' => [
        'https://www.googleapis.com/auth/calendar.events',
        'https://www.googleapis.com/auth/gmail.readonly',
    ],

    /*
    |--------------------------------------------------------------------------
    | Gmail import
    |--------------------------------------------------------------------------
    |
    | Every mail is imported (no rule-based filtering). OpenAI then reads each
    | one and hides those that match the user's "次から表示しない" rules;
    | interview dates, results and deadlines are never hidden.
    |
    */

    'gmail' => [
        // How far back to look on the first import.
        'initial_days' => (int) env('GMAIL_INITIAL_DAYS', 30),

        // Recruiting systems shared by many companies: their domain never
        // identifies a single company.
        'ats_domains' => [
            'axol.jp', 'i-webs.jp', 'snar.jp', 'sapsf.com', 'sapsf.jp', 'e2r.jp', 'hrmos.co',
            'talent-p.net', 'shl.co.jp', 'recruit-ms.co.jp', 'jposting.net', 'mynavi.jp',
        ],

        // Never imported.
        'ignore_labels' => ['SPAM', 'TRASH', 'SENT', 'DRAFT', 'CHAT'],

        // Longest body kept per mail (characters).
        'max_body_length' => 20000,

        // Longest body sent to OpenAI (characters).
        'ai_body_length' => 6000,

        // Mails judged per scheduler run.
        'assess_batch' => 20,
    ],

    /*
    |--------------------------------------------------------------------------
    | Initial data import
    |--------------------------------------------------------------------------
    |
    | Personal seed data (companies, ES answers, profile). The file is git
    | ignored; see database/seed-data/shukatsu.example.json for the format.
    |
    */

    'seed_file' => env('SHUKATSU_SEED_FILE', database_path('seed-data/shukatsu.json')),

];
