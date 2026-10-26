<?php

/*
|--------------------------------------------------------------------------
| Post categories
|--------------------------------------------------------------------------
|
| Editorial categories a post can belong to. The key is stored on
| posts.category. `aliases` are the words accepted as the first argument of
| the Telegram `/post` command, so "/post sarcasm https://..." files the
| draft under sarcasm. `tone` is the writing guidance handed to the AI model
| when it drafts a post in that category.
|
*/

return [
    'categories' => [
        'recent' => [
            'label' => 'Recent',
            'label_bn' => 'সাম্প্রতিক',
            'aliases' => ['recent', 'সাম্প্রতিক'],
            'tone' => 'Timely news update. Lead with what happened, when, and who is involved. Neutral, factual, and short. Name the source.',
        ],
        'politics' => [
            'label' => 'Politics',
            'label_bn' => 'রাজনীতি',
            'aliases' => ['politics', 'political', 'রাজনীতি'],
            'tone' => 'Political news or commentary. Stay factual and balanced, attribute every claim to its source, and avoid insults, rumours, or calls to action. Never invent quotes.',
        ],
        'analysis' => [
            'label' => 'Analysis',
            'label_bn' => 'বিশ্লেষণ',
            'aliases' => ['analysis', 'বিশ্লেষণ'],
            'tone' => 'Explain why it matters. Break the topic into a few clear points with context, numbers from the source, and a short takeaway. Calm and thoughtful.',
        ],
        'funny' => [
            'label' => 'Funny',
            'label_bn' => 'মজার',
            'aliases' => ['funny', 'humor', 'humour', 'মজার', 'মজা'],
            'tone' => 'Light-hearted and playful. Find the everyday humour in the topic with a short punchline. Never mock individuals for who they are.',
        ],
        'sarcasm' => [
            'label' => 'Sarcasm',
            'label_bn' => 'ব্যঙ্গ',
            'aliases' => ['sarcasm', 'sarcastic', 'satire', 'ব্যঙ্গ'],
            'tone' => 'Dry, witty sarcasm aimed at the situation or policy, not at private individuals. Keep it short and clever, and keep the underlying facts accurate.',
        ],
        'techy' => [
            'label' => 'Techy',
            'label_bn' => 'প্রযুক্তি',
            'aliases' => ['techy', 'tech', 'technology', 'প্রযুক্তি'],
            'tone' => 'Technology explained simply. Say what it is, why it matters to ordinary readers, and avoid jargon unless you explain it.',
        ],
    ],
];
