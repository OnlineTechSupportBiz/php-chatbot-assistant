<?php

/**
 * Copyright (c) 2026 Online Tech Support, LLC
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 */

/**
 * Chatbot create/edit form view.
 *
 * Create mode: $chatbot is undefined (or null).
 * Edit mode:   $chatbot is an array from ChatbotController::edit().
 */
\App\Auth\Session::start();
$errors  = \App\Auth\Session::getFlash('errors');
$success = \App\Auth\Session::getFlash('success');
$old     = \App\Auth\Session::getFlash('old') ?? [];

$isEdit   = isset($chatbot) && is_array($chatbot);
$action   = $isEdit ? '/chatbots/' . $chatbot['id'] : '/chatbots';
$pageTitle = ($isEdit ? 'Edit Chatbot' : 'Create Chatbot') . ' - ' . ($user['brand_name'] ?? 'Chatbot Assistant');
$submit   = $isEdit ? 'Update Chatbot' : 'Create Chatbot';

// Fields: use old input first (validation flash), then chatbot data (edit), then defaults
$name         = htmlspecialchars($old['name'] ?? ($isEdit ? $chatbot['name'] : ''));
$industry     = $old['industry'] ?? ($isEdit ? ($chatbot['industry'] ?? '') : '');
$systemPrompt = htmlspecialchars($old['system_prompt'] ?? ($isEdit ? ($chatbot['system_prompt'] ?? '') : ''));

// Cost & abuse protection: new chatbots get sensible defaults; existing keep their value
if ($isEdit) {
    $dailyTokenBudget    = $old['daily_token_budget'] ?? $chatbot['daily_token_budget'];
    $dailyTokenBudget    = $dailyTokenBudget === '' || $dailyTokenBudget === null ? 100000 : (int) $dailyTokenBudget;
    $rateLimitPerSession = $old['rate_limit_per_session'] ?? $chatbot['rate_limit_per_session'];
    $rateLimitPerSession = $rateLimitPerSession === '' || $rateLimitPerSession === null ? 30 : (int) $rateLimitPerSession;
    $maxMessageLength    = $old['max_message_length'] ?? $chatbot['max_message_length'];
    $maxMessageLength    = $maxMessageLength === '' || $maxMessageLength === null ? '' : (int) $maxMessageLength;
    $maxMessagesPerConv  = $old['max_messages_per_conversation'] ?? $chatbot['max_messages_per_conversation'];
    $maxMessagesPerConv  = $maxMessagesPerConv === '' || $maxMessagesPerConv === null ? '' : (int) $maxMessagesPerConv;
} else {
    $dailyTokenBudget    = $old['daily_token_budget'] ?? 100000;
    $rateLimitPerSession = $old['rate_limit_per_session'] ?? 30;
    $maxMessageLength    = $old['max_message_length'] ?? '';
    $maxMessagesPerConv  = $old['max_messages_per_conversation'] ?? '';
}

// ── Industry → system prompt presets (loaded from DB by controller) ─────────
$industryPresetsGrouped = $industryPresetsGrouped ?? [];
$industryPresets = $industryPresetsGrouped;

// Flatten for industry matching in edit mode
$presetByPrompt = [];
foreach ($industryPresets as $cat => $presets) {
    foreach ($presets as $i => $p) {
        $presetByPrompt[$p['prompt']] = [
            'category' => $cat,
            'label' => $p['label'],
        ];
    }
}

// Determine selected industry in edit mode by matching stored system_prompt
$selectedCategory = '';
$selectedLabel = '';
if ($industry !== '' && isset($presetByPrompt[$industry])) {
    // industry field holds the category name (legacy)
    $selectedCategory = $industry;
} elseif ($isEdit && !empty($chatbot['system_prompt'])) {
    $sp = $chatbot['system_prompt'];
    if (isset($presetByPrompt[$sp])) {
        $selectedCategory = $presetByPrompt[$sp]['category'];
        $selectedLabel = $presetByPrompt[$sp]['label'];
    } elseif ($isEdit && !empty($chatbot['industry'])) {
        $selectedCategory = $chatbot['industry'];
    }
} elseif ($isEdit && !empty($chatbot['industry'])) {
    $selectedCategory = $chatbot['industry'];
}
$selectedCategoryEsc = htmlspecialchars($selectedCategory);
$selectedLabelEsc = htmlspecialchars($selectedLabel);

// Model config defaults
$defaultConfig = ['temperature' => 0.7, 'max_tokens' => 1024, 'model' => 'gpt-4.1-mini'];
$modelConfig   = $isEdit && isset($chatbot['model_config']) && is_array($chatbot['model_config'])
    ? $chatbot['model_config'] : $defaultConfig;
$temperature = htmlspecialchars((string) ($modelConfig['temperature'] ?? 0.7));
$maxTokens   = htmlspecialchars((string) ($modelConfig['max_tokens'] ?? 1024));
$model       = htmlspecialchars($modelConfig['model'] ?? 'gpt-4.1-mini');

// Retrieval strategy
$retrievalStrategy = $old['retrieval_strategy'] ?? ($isEdit ? ($chatbot['retrieval_strategy'] ?? 'traditional_rag') : 'traditional_rag');

// ── Widget theme presets ──────────────────────────────────────────────────
$widgetThemes = [
    ''               => ['label' => '✏️ Custom', 'primary' => '#0d6efd', 'gradient_to' => '', 'accent' => '', 'header_text' => '#ffffff', 'desc' => 'Choose your own colors below'],
    'default-blue'   => ['label' => '🔵 Default Blue',  'primary' => '#0d6efd', 'gradient_to' => '', 'accent' => '', 'header_text' => '#ffffff', 'desc' => 'Clean Bootstrap blue — classic and professional'],
    'emerald-green'  => ['label' => '🟢 Emerald Green', 'primary' => '#10b981', 'gradient_to' => '', 'accent' => '', 'header_text' => '#ffffff', 'desc' => 'Fresh, growth-oriented green'],
    'royal-purple'   => ['label' => '🟣 Royal Purple',  'primary' => '#7c3aed', 'gradient_to' => '', 'accent' => '', 'header_text' => '#ffffff', 'desc' => 'Bold and creative purple'],
    'crimson-red'    => ['label' => '🔴 Crimson Red',   'primary' => '#dc2626', 'gradient_to' => '', 'accent' => '', 'header_text' => '#ffffff', 'desc' => 'Strong, urgent red for support teams'],
    'amber-orange'   => ['label' => '🟠 Amber Orange',  'primary' => '#f59e0b', 'gradient_to' => '', 'accent' => '', 'header_text' => '#1e293b', 'desc' => 'Warm, friendly amber — dark header text for contrast'],
    'ocean-teal'     => ['label' => '🩵 Ocean Teal',    'primary' => '#0d9488', 'gradient_to' => '', 'accent' => '', 'header_text' => '#ffffff', 'desc' => 'Calming teal for professional service bots'],
    'rose-pink'      => ['label' => '🩷 Rose Pink',     'primary' => '#e11d48', 'gradient_to' => '', 'accent' => '', 'header_text' => '#ffffff', 'desc' => 'Vibrant rose for brand-forward businesses'],
    'slate-dark'     => ['label' => '⚫ Slate Dark',    'primary' => '#1e293b', 'gradient_to' => '', 'accent' => '', 'header_text' => '#f8fafc', 'desc' => 'Sleek dark slate for modern premium brands'],
    'sky-blue'       => ['label' => '💙 Sky Blue',      'primary' => '#0284c7', 'gradient_to' => '', 'accent' => '', 'header_text' => '#ffffff', 'desc' => 'Bright, trustworthy sky blue'],
    'lime-green'     => ['label' => '💚 Lime Green',    'primary' => '#65a30d', 'gradient_to' => '', 'accent' => '', 'header_text' => '#ffffff', 'desc' => 'Energetic lime for eco-friendly brands'],
    'deep-forest'    => ['label' => '🌲 Deep Forest',   'primary' => '#14532d', 'gradient_to' => '#0a3018', 'accent' => '#eab308', 'header_text' => '#ffffff', 'desc' => 'Rich green with golden accent — grounded and natural'],
    'steel-navy'     => ['label' => '⚓ Steel Navy',     'primary' => '#1a365d', 'gradient_to' => '#162240', 'accent' => '#d35400', 'header_text' => '#ffffff', 'desc' => 'Dark navy with burnt orange accent — rugged and reliable'],
    'construction'   => ['label' => '🏗️ Construction',   'primary' => '#1a365d', 'gradient_to' => '#162240', 'accent' => '#ff7800', 'header_text' => '#ffffff', 'desc' => 'Navy with bright orange accent — bold and industrial'],
    'stone'          => ['label' => '🪨 Stone',          'primary' => '#292524', 'gradient_to' => '#1a1716', 'accent' => '#f59e0b', 'header_text' => '#ffffff', 'desc' => 'Earthy brown with warm gold accent — sturdy and honest'],
    'twilight'       => ['label' => '🌆 Twilight',       'primary' => '#6b21a8', 'gradient_to' => '#581c87', 'accent' => '#e11d48', 'header_text' => '#ffffff', 'desc' => 'Deep purple with rose accent — bold and creative'],
    'indigo-night'   => ['label' => '🌙 Indigo Night',  'primary' => '#3730a3', 'gradient_to' => '#2c2585', 'accent' => '#d97706', 'header_text' => '#ffffff', 'desc' => 'Indigo with amber accent — thoughtful and premium'],
    'midnight'       => ['label' => '🌃 Midnight',       'primary' => '#0f172a', 'gradient_to' => '#070b15', 'accent' => '#22c55e', 'header_text' => '#ffffff', 'desc' => 'Deep night blue with green accent — sleek and modern'],
    'deep-teal'      => ['label' => '🌊 Deep Teal',     'primary' => '#075985', 'gradient_to' => '#06486b', 'accent' => '#059669', 'header_text' => '#ffffff', 'desc' => 'Rich teal with emerald accent — calm and trustworthy'],
    'burgundy'       => ['label' => '🍷 Burgundy',       'primary' => '#7f1d1d', 'gradient_to' => '#5c1515', 'accent' => '#dc2626', 'header_text' => '#ffffff', 'desc' => 'Deep wine with red accent — sophisticated and warm'],
    'slate'          => ['label' => '🏔️ Slate',          'primary' => '#1e293b', 'gradient_to' => '#141d28', 'accent' => '#64748b', 'header_text' => '#ffffff', 'desc' => 'Cool gray-blue — neutral and professional'],
    'sea-glass'      => ['label' => '🫧 Sea Glass',     'primary' => '#0d6e6e', 'gradient_to' => '#0a5c5c', 'accent' => '#0b7a5a', 'header_text' => '#ffffff', 'desc' => 'Teal dual-tone with forest accent — fresh and healing'],
    'royal-blue'     => ['label' => '👑 Royal Blue',    'primary' => '#1e3a8a', 'gradient_to' => '#152b6e', 'accent' => '#db2777', 'header_text' => '#ffffff', 'desc' => 'Classic blue with pink accent — confident and dynamic'],
    'slate-blue'     => ['label' => '🔷 Slate Blue',    'primary' => '#1e3a5f', 'gradient_to' => '#15294a', 'accent' => '#dc2626', 'header_text' => '#ffffff', 'desc' => 'Muted navy with red accent — stable and assertive'],
    'dark-umber'     => ['label' => '🟤 Dark Umber',    'primary' => '#1e293b', 'gradient_to' => '#0f172a', 'accent' => '#92400e', 'header_text' => '#ffffff', 'desc' => 'Deep slate with brown accent — grounded and authoritative'],
    'deep-navy'      => ['label' => '⚫ Deep Navy',      'primary' => '#0f172a', 'gradient_to' => '#090e1c', 'accent' => '#ea580c', 'header_text' => '#ffffff', 'desc' => 'Near-black navy with orange accent — bold and energetic'],
    'charcoal'       => ['label' => '🖤 Charcoal',       'primary' => '#27272a', 'gradient_to' => '#1a1a1d', 'accent' => '#ca8a04', 'header_text' => '#ffffff', 'desc' => 'Dark charcoal with gold accent — industrial and premium'],
    'plum'           => ['label' => '🍇 Plum',           'primary' => '#7b1fa2', 'gradient_to' => '#59167a', 'accent' => '#ff6d00', 'header_text' => '#ffffff', 'desc' => 'Vibrant plum with orange accent — creative and energetic'],
    'wine'           => ['label' => '🍒 Wine',           'primary' => '#4c0519', 'gradient_to' => '#2d030f', 'accent' => '#d946ef', 'header_text' => '#ffffff', 'desc' => 'Deep merlot with magenta accent — bold and luxurious'],
    'jade'           => ['label' => '💎 Jade',           'primary' => '#065f46', 'gradient_to' => '#033d2f', 'accent' => '#f59e0b', 'header_text' => '#ffffff', 'desc' => 'Rich jade with gold accent — prosperous and natural'],
    'amethyst'       => ['label' => '💜 Amethyst',       'primary' => '#5b21b6', 'gradient_to' => '#3b1078', 'accent' => '#f472b6', 'header_text' => '#ffffff', 'desc' => 'Deep purple with pink accent — whimsical and creative'],
    'classic-blue'   => ['label' => '💠 Classic Blue',  'primary' => '#2563eb', 'gradient_to' => '#1d4ed8', 'accent' => '#7c3aed', 'header_text' => '#ffffff', 'desc' => 'Clean blue with purple accent — modern SaaS standard'],
    'slate-steel'    => ['label' => '🔩 Slate Steel',    'primary' => '#1b2838', 'gradient_to' => '#121d2c', 'accent' => '#0284c7', 'header_text' => '#ffffff', 'desc' => 'Industrial slate with cyan accent — technical and precise'],
];

// Styling defaults
$defaultStyling = [
    'primary_color'      => '#0d6efd',
    'header_text_color'  => '#ffffff',
    'header_gradient_to' => '',
    'accent_color'       => '',
    'header_icon'        => '',
    'header_subtitle'    => '',
    'placeholder_icon'   => '',
    'placeholder_title'  => '',
    'placeholder_text'   => '',
    'position'           => 'bottom-right',
    'bot_name'           => 'Assistant',
    'widget_theme'       => '',
    'panel_theme'        => 'light',
];
$styling        = $isEdit && isset($chatbot['styling']) && is_array($chatbot['styling'])
    ? $chatbot['styling'] : $defaultStyling;
$primaryColor       = htmlspecialchars($styling['primary_color'] ?? '#0d6efd');
$headerTextColor    = htmlspecialchars($styling['header_text_color'] ?? '#ffffff');
$headerGradientTo   = htmlspecialchars($styling['header_gradient_to'] ?? '');
$accentColor        = htmlspecialchars($styling['accent_color'] ?? '');
$headerIcon         = htmlspecialchars($styling['header_icon'] ?? '');
$headerSubtitle     = htmlspecialchars($styling['header_subtitle'] ?? '');
$placeholderIcon    = htmlspecialchars($styling['placeholder_icon'] ?? '');
$placeholderTitle   = htmlspecialchars($styling['placeholder_title'] ?? '');
$placeholderText    = htmlspecialchars($styling['placeholder_text'] ?? '');
$position           = htmlspecialchars($styling['position'] ?? 'bottom-right');
$botName            = htmlspecialchars($styling['bot_name'] ?? 'Assistant');
$widgetTheme        = htmlspecialchars($styling['widget_theme'] ?? '');

// Icon options for header and placeholder dropdowns
$iconOptions = [
    ''    => 'None',
    '💬'  => '💬 Chat',
    '🤖'  => '🤖 Robot / AI',
    '🚀'  => '🚀 Rocket',
    '⭐'  => '⭐ Star',
    '🎯'  => '🎯 Target',
    '🔥'  => '🔥 Fire',
    '💡'  => '💡 Lightbulb',
    '👋'  => '👋 Wave',
    '❓'  => '❓ Question',
    '✅'  => '✅ Checkmark',
    '🔔'  => '🔔 Bell',
    '📧'  => '📧 Email',
    '📱'  => '📱 Phone',
    '📝'  => '📝 Document',
    '🗨️'  => '🗨️ Speech',
    '👤'  => '👤 Person',
    '👑'  => '👑 Crown',
    '💎'  => '💎 Diamond',
    '🏆'  => '🏆 Trophy',
    '🎁'  => '🎁 Gift',
    '🔒'  => '🔒 Lock',
    '🌍'  => '🌍 Globe',
    '☕'  => '☕ Coffee',
    '🎵'  => '🎵 Music',
    '🖥️'  => '🖥️ Monitor',
    '🎮'  => '🎮 Gaming',
    '🌿'  => '🌿 Agriculture',
    '🔧'  => '🔧 Auto Repair',
    '🏗️'  => '🏗️ Construction',
    '🛒'  => '🛒 E-Commerce',
    '📚'  => '📚 Education',
    '⚡'  => '⚡ Energy',
    '💰'  => '💰 Finance',
    '💪'  => '💪 Fitness',
    '🍕'  => '🍕 Food & Beverage',
    '🏛️'  => '🏛️ Government',
    '❤️'  => '❤️ Healthcare',
    '🏠'  => '🏠 Home Services',
    '✈️'  => '✈️ Hospitality',
    '👥'  => '👥 HR',
    '🛡️'  => '🛡️ Insurance',
    '⚖️'  => '⚖️ Legal',
    '🚚'  => '🚚 Logistics',
    '🏭'  => '🏭 Manufacturing',
    '📊'  => '📊 Marketing',
    '🎬'  => '🎬 Media',
    '🤝'  => '🤝 Nonprofit',
    '🐾'  => '🐾 Pet Services',
    '💊'  => '💊 Pharma',
    '🏡'  => '🏡 Real Estate',
    '💻'  => '💻 Technology',
    '📡'  => '📡 Telecommunications',
];

// Status (edit only)
$currentStatus = $isEdit ? ($chatbot['status'] ?? 'active') : 'active';
?><style>
    .preview-color { width: 2rem; height: 2rem; border-radius: 50%; display: inline-block; vertical-align: middle; }
    .industry-group { font-weight: 600; }
    .card-header h5 { margin: 0; font-size: 1rem; }
    /* ── Collapsible Sections ──────────────────────────────────────── */
    .collapse-toggle { cursor: pointer; user-select: none; }
    .collapse-toggle::after { content: ' \25BC'; font-size: 0.7rem; opacity: 0.5; }
    .collapse-toggle.collapsed::after { content: ' \25B6'; }
    /* ── Widget Live Preview ──────────────────────────────────────── */
    #widget-preview {
        position: fixed; z-index: 2147483647;
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        bottom: var(--widget-bottom, 20px);
        right: var(--widget-right, 20px);
        left: var(--widget-left, auto);
    }
    .preview-bubble {
        width: 60px; height: 60px; border-radius: 50%;
        background: var(--widget-primary, #0d6efd);
        color: #fff; border: none; cursor: pointer;
        display: flex; align-items: center; justify-content: center;
        box-shadow: 0 4px 12px rgba(0,0,0,0.2);
        transition: transform 0.2s;
    }
    .preview-bubble:hover { transform: scale(1.1); }
    .preview-bubble svg { width: 28px; height: 28px; display: block; }
    .preview-panel {
        max-width: calc(100vw - 40px);
        width: 360px; height: 520px; border-radius: 16px;
        background: var(--widget-surface); border: 1px solid var(--widget-border-color); box-shadow: 0 8px 32px rgba(0,0,0,0.4);
        display: none; flex-direction: column; overflow: hidden;
        position: absolute; bottom: 72px; right: 0;
    }
    .preview-panel.open { display: flex; }
    @media (max-width: 420px) {
        .preview-panel { width: calc(100vw - 40px); height: 60vh; }
    }
    .preview-header {
        background: var(--widget-header-bg, var(--widget-primary, #0d6efd));
        color: var(--widget-header-text, #ffffff);
        padding: 12px 16px; display: flex; align-items: center; justify-content: space-between; gap: 10px;
        border: none;
        border-bottom: 3px solid var(--widget-accent, var(--widget-primary, #0d6efd));
    }
    .preview-header-left { display: flex; align-items: center; gap: 10px; min-width: 0; flex: 1; }
    .preview-header-icon {
        width: 36px; height: 36px; border-radius: 50%;
        background: rgba(0,0,0,0.2);
        display: flex; align-items: center; justify-content: center;
        font-size: 18px; line-height: 1; flex-shrink: 0;
    }
    .preview-header-text { display: flex; flex-direction: column; min-width: 0; }
    .preview-header-title { font-weight: 600; font-size: 15px; line-height: 1.3; }
    .preview-header-subtitle { font-size: 11px; opacity: 0.8; line-height: 1.3; margin-top: 1px; }
    .preview-close {
        background: none; border: none; color: var(--widget-header-text, #ffffff);
        cursor: pointer; font-size: 20px; opacity: 0.8;
    }
    .preview-close:hover { opacity: 1; }
    .preview-body {
        flex: 1; overflow-y: auto; padding: 12px; background: var(--widget-body-bg, var(--widget-surface));
    }
    .preview-placeholder {
        text-align: center; color: var(--widget-text-secondary); padding: 40px 20px 20px;
    }
    .preview-placeholder-icon { font-size: 36px; line-height: 1; margin-bottom: 10px; opacity: 0.6; }
    .preview-placeholder-title { font-weight: 600; font-size: 15px; color: var(--widget-placeholder-title, #2c3e50); margin-bottom: 4px; }
    .preview-placeholder-text { font-size: 14px; line-height: 1.5; }
    .preview-footer {
        padding: 10px 12px; border-top: 1px solid var(--widget-border-color); background: var(--widget-surface);
        display: flex; gap: 8px;
    }
    .preview-footer input {
        flex: 1; border: 1px solid var(--widget-border-color); border-radius: 20px;
        padding: 8px 14px; font-size: 14px; outline: none;
        background: var(--widget-surface-2); color: var(--widget-text-secondary);
    }
    .preview-send {
        background: var(--widget-accent, var(--widget-primary, #0d6efd)); color: #fff; border: none;
        border-radius: 50%; width: 36px; height: 36px; cursor: pointer;
        display: flex; align-items: center; justify-content: center;
    }
    .preview-send svg { width: 18px; height: 18px; display: block; }
    /* ── Panel Theme CSS Variables (overridden by JS) ────────────── */
    /* Light panel defaults */
    :root {
        --surface: #ffffff;
        --primary: #f5f7fa;
        --text-secondary: #8899b4;
        --border-color: rgba(0,0,0,0.08);
        --surface-2: #f0f2f5;
    }
    /* ── Responsive tweaks ─────────────────────────────────────────── */
    @media (max-width: 767.98px) {
        .container-fluid { padding-left: 10px; padding-right: 10px; }
        .preview-panel { height: 50vh; }
        pre code { font-size: 0.75rem; word-break: break-all; white-space: pre-wrap; }
    }
</style>
<?php ob_start(); ?>
<div class="container-fluid">
    <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="/chatbots">Chatbots</a></li>
            <?php if ($isEdit): ?>
                <li class="breadcrumb-item"><a href="/chatbots/<?= (int)$chatbot['id'] ?>"><?= htmlspecialchars($chatbot['name']) ?></a></li>
                <li class="breadcrumb-item active" aria-current="page">Edit</li>
            <?php else: ?>
                <li class="breadcrumb-item active" aria-current="page">New Chatbot</li>
            <?php endif; ?>
        </ol>
    </nav>
    <?php if ($success): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>
    <?php if (is_array($errors)): ?>
        <div class="alert alert-danger">
            <ul class="mb-0">
                <?php foreach ($errors as $err): ?>
                    <li><?= htmlspecialchars($err) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <h1 class="mb-4"><?= $pageTitle ?></h1>

    <form method="POST" action="<?= $action ?>">
        <input type="hidden" name="_csrf" value="<?= \App\Auth\Session::csrfToken() ?>">
        <?php if ($isEdit): ?>
            <input type="hidden" name="_method" value="PUT">
        <?php endif; ?>

        <!-- Status (edit only) -->
        <?php if ($isEdit): ?>
        <div class="card mb-4">
            <div class="card-header">
                <h2 class="h5">Status</h2>
            </div>
            <div class="card-body">
            <div class="mb-3">
                <div class="form-check form-check-inline">
                    <input class="form-check-input" type="radio" name="status" id="status-active"
                           value="active" <?= $currentStatus === 'active' ? 'checked' : '' ?>>
                    <label class="form-check-label" for="status-active">Active</label>
                </div>
                <div class="form-check form-check-inline">
                    <input class="form-check-input" type="radio" name="status" id="status-paused"
                           value="paused" <?= $currentStatus === 'paused' ? 'checked' : '' ?>>
                    <label class="form-check-label" for="status-paused">Paused</label>
                </div>
            </div>
        </div>
        </div>
        <?php endif; ?>

        <!-- Basic Info -->
        <div class="card mb-4">
            <div class="card-header">
                <h2 class="h5">Basic Information</h2>
            </div>
            <div class="card-body">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="name" class="form-label">Chatbot Name <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="name" name="name"
                           value="<?= $name ?>" required maxlength="255">
                </div>
                <div class="col-md-6 mb-3">
                    <label for="industry" class="form-label">Industry / Prompt Template</label>
                    <select class="form-select" id="industry" name="industry">
                        <?php
                        $first = true;
                        foreach ($industryPresets as $category => $presets):
                            // Skip "Custom" as the first optgroup — render it separately
                            if ($first) {
                                $first = false;
                                // Custom is first — render as plain options for the "top of dropdown" behavior
                                echo '<option value="" data-prompt=""';
                                if ($selectedCategory === '') echo ' selected';
                                echo '>— Select a template —</option>';
                                echo '<option value="__custom__" data-prompt=""';
                                if ($selectedCategory === '__custom__') echo ' selected';
                                echo '>✏️ Custom (write your own prompt)</option>';
                                continue;
                            }
                        ?>
                        <optgroup label="<?= htmlspecialchars($category) ?>">
                            <?php foreach ($presets as $p):
                                $promptVal = htmlspecialchars($p['prompt'], ENT_QUOTES);
                                $label = htmlspecialchars($p['label'], ENT_QUOTES);
                                // Check if this prompt matches the current system prompt or if category matches
                                $sel = ($selectedCategory === $category && $selectedLabel === $p['label'])
                                    || ($isEdit && $chatbot['system_prompt'] === $p['prompt']);
                            ?>
                            <option value="<?= htmlspecialchars($category) ?>"
                                    data-prompt="<?= $promptVal ?>"
                                    data-category="<?= htmlspecialchars($category) ?>"
                                    <?= $sel ? 'selected' : '' ?>>
                                <?= $label ?>
                            </option>
                            <?php endforeach; ?>
                        </optgroup>
                        <?php endforeach; ?>
                    </select>
                    <div class="form-text">Select an industry to auto-fill the system prompt. Choose "Custom" to write your own.</div>
                </div>
            </div>

            <div class="mb-3">
                <label for="system_prompt" class="form-label">System Prompt</label>
                <textarea class="form-control" id="system_prompt" name="system_prompt" rows="5"
                          placeholder="You are a helpful assistant for [company]. Answer questions based on the provided documents."><?= $systemPrompt ?></textarea>
                <div class="form-text">
                    Instructions that define your chatbot's personality and behavior.
                    <span id="prompt-source" class="text-muted"></span>
                </div>
            </div>
        </div>
    </div>

        <!-- Model Configuration -->
        <div class="card mb-4">
            <div class="card-header">
                <h2 class="h5">Model Configuration</h2>
            </div>
            <div class="card-body">
            <div class="row">
                <div class="col-md-4 mb-3">
                    <label for="model" class="form-label">Model</label>
                    <select class="form-select" id="model" name="model">
                        <option value="gpt-4.1-mini" <?= $model === 'gpt-4.1-mini' ? 'selected' : '' ?>>GPT-4.1-mini</option>
                        <option value="gpt-4.1" <?= $model === 'gpt-4.1' ? 'selected' : '' ?>>GPT-4.1</option>
                        <option value="gpt-5-nano" <?= $model === 'gpt-5-nano' ? 'selected' : '' ?>>GPT-5-nano</option>
                        <option value="gpt-5-mini" <?= $model === 'gpt-5-mini' ? 'selected' : '' ?>>GPT-5-mini</option>
                        <option value="gpt-5" <?= $model === 'gpt-5' ? 'selected' : '' ?>>GPT-5</option>
                        <option value="gpt-5-pro" <?= $model === 'gpt-5-pro' ? 'selected' : '' ?>>GPT-5-Pro</option>
                        <option value="gpt-5.1" <?= $model === 'gpt-5.1' ? 'selected' : '' ?>>GPT-5.1</option>
                        <option value="gpt-5.2" <?= $model === 'gpt-5.2' ? 'selected' : '' ?>>GPT-5.2</option>
                        <option value="gpt-5.2-pro" <?= $model === 'gpt-5.2-pro' ? 'selected' : '' ?>>GPT-5.2-Pro</option>
                        <option value="gpt-5.3-codex" <?= $model === 'gpt-5.3-codex' ? 'selected' : '' ?>>GPT-5.3-Codex</option>
                        <option value="gpt-5.4-nano" <?= $model === 'gpt-5.4-nano' ? 'selected' : '' ?>>GPT-5.4-nano</option>
                        <option value="gpt-5.4-mini" <?= $model === 'gpt-5.4-mini' ? 'selected' : '' ?>>GPT-5.4-mini</option>
                        <option value="gpt-5.4" <?= $model === 'gpt-5.4' ? 'selected' : '' ?>>GPT-5.4</option>
                        <option value="gpt-5.4-pro" <?= $model === 'gpt-5.4-pro' ? 'selected' : '' ?>>GPT-5.4-Pro</option>
                        <option value="gpt-5.5" <?= $model === 'gpt-5.5' ? 'selected' : '' ?>>GPT-5.5</option>
                        <option value="gpt-5.5-pro" <?= $model === 'gpt-5.5-pro' ? 'selected' : '' ?>>GPT-5.5-Pro</option>
                        <option value="gpt-5.6-luna" <?= $model === 'gpt-5.6-luna' ? 'selected' : '' ?>>GPT-5.6-Luna</option>
                        <option value="gpt-5.6-terra" <?= $model === 'gpt-5.6-terra' ? 'selected' : '' ?>>GPT-5.6-Terra</option>
                        <option value="gpt-5.6-sol" <?= $model === 'gpt-5.6-sol' ? 'selected' : '' ?>>GPT-5.6-Sol</option>
                    </select>
                </div>
                <div class="col-md-4 mb-3">
                    <label for="temperature" class="form-label">Temperature</label>
                    <input type="number" class="form-control" id="temperature" name="temperature"
                           value="<?= $temperature ?>" min="0" max="2" step="0.1">
                    <div class="form-text">0 = deterministic, 2 = very creative.</div>
                </div>
                <div class="col-md-4 mb-3">
                    <label for="max_tokens" class="form-label">Max Tokens</label>
                    <input type="number" class="form-control" id="max_tokens" name="max_tokens"
                           value="<?= $maxTokens ?>" min="64" max="16384" step="1">
                    <div class="form-text">
                        Max response length. <strong>Short Q&A:</strong> 256–512 &middot;
                        <strong>Default:</strong> 1024 &middot;
                        <strong>Long answers/code:</strong> 2048–4096 &middot;
                        <strong>Max output:</strong> 16384.
                        Higher = more tokens billed.
                    </div>
                    <div class="form-text">
                        <strong>Cost note:</strong> max_tokens affects cost directly — you pay per output token.
                        For <code>gpt-4.1-mini</code> ($1.60/1M output), 1024 tokens is ~$0.0016/reply.
                        16384 would be 16&times; that (~$0.0262). Higher-end models cost proportionally more.
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Lead Capture -->
    <div class="card mb-4">
        <div class="card-header d-flex align-items-center justify-content-between">
            <h2 class="h5 mb-0">Lead Capture</h2>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-12">
                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" role="switch"
                               id="lead_capture_enabled" name="lead_capture_enabled" value="1"
                               <?= !empty($chatbot['lead_capture_enabled']) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="lead_capture_enabled">
                            <strong>Enable Lead Capture</strong>
                        </label>
                    </div>
                    <div class="form-text">
                        When enabled, the chatbot will naturally ask visitors for their name, email address,
                        and phone number during the conversation. Once all three are collected, they are
                        stored as leads and viewable from the chatbot's management page.
                    </div>
                    <div class="form-text my-2">
                        <strong>How it works:</strong> The AI asks for each piece of information one at a time.
                        When it detects all three fields have been provided, the lead is automatically saved
                        to this chatbot's lead list.
                    </div>
                </div>
            </div>
        </div>
    </div>

        <!-- Retrieval Strategy -->
        <div class="card mb-4">
            <div class="card-header d-flex align-items-center justify-content-between">
                <h2 class="h5 mb-0">Retrieval Strategy</h2>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-12">
                        <div class="mb-3">
                            <label class="form-label">Document Retrieval Method</label>
                            <div class="d-flex gap-4 mt-1">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="retrieval_strategy"
                                           id="strategy-traditional" value="traditional_rag"
                                           <?= ($retrievalStrategy ?? 'traditional_rag') === 'traditional_rag' ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="strategy-traditional">
                                        <strong>Traditional RAG</strong>
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="retrieval_strategy"
                                           id="strategy-page-index" value="page_index"
                                           <?= ($retrievalStrategy ?? 'traditional_rag') === 'page_index' ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="strategy-page-index">
                                        <strong>PageIndex</strong>
                                    </label>
                                </div>
                            </div>
                            <div class="form-text my-2">
                                <strong>Traditional RAG:</strong> Documents are chunked and embedded into a vector
                                database. At query time, the chatbot uses embedding similarity search to find relevant
                                passages. Best for most use cases &mdash; fast, proven, and accurate.
                            </div>
                            <div class="form-text mt-1">
                                <strong>PageIndex:</strong> Documents are parsed into a hierarchical table of contents
                                structure. At query time, an LLM reasons over the outline to find the most relevant
                                section &mdash; no embedding needed. Best for well-structured documents with clear
                                headings and sections. Requires additional processing after document upload.
                            </div>
                            <div class="form-text mt-2">
                                Note: Changing the strategy after documents have been indexed will not automatically
                                reprocess existing documents. You must re-upload and re-index documents after
                                switching.
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    <!-- Styling -->
        <div class="card mb-4">
            <div class="card-header">
                <h2 class="h5">Widget Styling</h2>
            </div>
            <div class="card-body">

            <!-- Theme Selector -->
            <div class="row mb-3">
                <div class="col-md-6">
                    <label for="widget_theme" class="form-label">🎨 Theme Preset</label>
                    <select class="form-select" id="widget_theme" name="widget_theme">
                        <?php foreach ($widgetThemes as $key => $theme): ?>
                        <option value="<?= htmlspecialchars($key) ?>"
                                data-primary="<?= htmlspecialchars($theme['primary']) ?>"
                                data-gradient-to="<?= htmlspecialchars($theme['gradient_to']) ?>"
                                data-accent="<?= htmlspecialchars($theme['accent']) ?>"
                                data-header-text="<?= htmlspecialchars($theme['header_text']) ?>"
                                <?= $widgetTheme === $key ? 'selected' : '' ?>>
                            <?= htmlspecialchars($theme['label']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="form-text" id="theme-desc">
                        <?= htmlspecialchars($widgetThemes[$widgetTheme]['desc'] ?? 'Choose a theme or set custom colors below') ?>
                    </div>
                </div>
            </div>

            <!-- Panel Theme Toggle -->
            <div class="row mb-3">
                <div class="col-md-6">
                    <label class="form-label">Widget Panel Theme</label>
                    <div class="d-flex gap-3 mt-1">
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="panel_theme" id="panel-theme-light"
                                   value="light" <?= ($styling['panel_theme'] ?? 'light') === 'light' ? 'checked' : '' ?>>
                            <label class="form-check-label" for="panel-theme-light">
                                Light
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="panel_theme" id="panel-theme-dark"
                                   value="dark" <?= ($styling['panel_theme'] ?? 'light') === 'dark' ? 'checked' : '' ?>>
                            <label class="form-check-label" for="panel-theme-dark">
                                Dark
                            </label>
                        </div>
                    </div>
                    <div class="form-text">Light panel is white/silver; dark panel is navy/charcoal. Presets look best with dark mode unless you pick a light-friendly header text color.</div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-4 mb-3">
                    <label for="bot_name" class="form-label">Bot Display Name</label>
                    <input type="text" class="form-control" id="bot_name" name="bot_name"
                           value="<?= $botName ?>" maxlength="100">
                </div>
                <div class="col-md-2 mb-3">
                    <label for="primary_color" class="form-label">
                        Primary &nbsp;<span class="preview-color" id="color-preview" style="background-color: <?= $primaryColor ?>;"></span>
                    </label>
                    <input type="color" class="form-control form-control-color" id="primary_color"
                           name="primary_color" value="<?= $primaryColor ?>">
                </div>
                <div class="col-md-2 mb-3">
                    <label for="header_gradient_to" class="form-label">
                        Gradient &nbsp;<span class="preview-color" id="gradient-preview" style="background-color: <?= $headerGradientTo ?: $primaryColor ?>;"></span>
                    </label>
                    <input type="color" class="form-control form-control-color" id="header_gradient_to"
                           name="header_gradient_to" value="<?= $headerGradientTo ?: $primaryColor ?>">
                    <div class="form-text">Leave same as Primary for solid header.</div>
                </div>
                <div class="col-md-4 mb-3">
                    <label for="accent_color" class="form-label">
                        Accent &nbsp;<span class="preview-color" id="accent-preview" style="background-color: <?= $accentColor ?: $primaryColor ?>;"></span>
                    </label>
                    <input type="color" class="form-control form-control-color" id="accent_color"
                           name="accent_color" value="<?= $accentColor ?: $primaryColor ?>">
                    <div class="form-text">Send button, links, hover effects. Leave blank to match Primary.</div>
                </div>
                <div class="col-md-4 mb-3">
                    <label for="header_text_color" class="form-label">
                        Header Text &nbsp;<span class="preview-color" id="header-text-preview" style="background-color: <?= $headerTextColor ?>;"></span>
                    </label>
                    <input type="color" class="form-control form-control-color" id="header_text_color"
                           name="header_text_color" value="<?= $headerTextColor ?>">
                </div>
            </div>
            <div class="row">
                <div class="col-md-3 mb-3">
                    <label for="header_icon" class="form-label">Header Icon</label>
                    <select class="form-select" id="header_icon" name="header_icon">
                        <?php foreach ($iconOptions as $val => $label): ?>
                        <option value="<?= htmlspecialchars($val) ?>" <?= $headerIcon === $val ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3 mb-3">
                    <label for="header_subtitle" class="form-label">Header Subtitle</label>
                    <input type="text" class="form-control" id="header_subtitle" name="header_subtitle"
                           value="<?= $headerSubtitle ?>" maxlength="100" placeholder="e.g. Online">
                </div>
                <div class="col-md-3 mb-3">
                    <label for="position" class="form-label">Widget Position</label>
                    <select class="form-select" id="position" name="position">
                        <option value="bottom-right" <?= $position === 'bottom-right' ? 'selected' : '' ?>>Bottom Right</option>
                        <option value="bottom-left"  <?= $position === 'bottom-left' ? 'selected' : '' ?>>Bottom Left</option>
                    </select>
                </div>
            </div>

            <div class="row">
                <div class="col-md-2 mb-3">
                    <label for="placeholder_icon" class="form-label">Placeholder Icon</label>
                    <select class="form-select" id="placeholder_icon" name="placeholder_icon">
                        <?php foreach ($iconOptions as $val => $label): ?>
                        <option value="<?= htmlspecialchars($val) ?>" <?= $placeholderIcon === $val ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-5 mb-3">
                    <label for="placeholder_title" class="form-label">Placeholder Title</label>
                    <input type="text" class="form-control" id="placeholder_title" name="placeholder_title"
                           value="<?= $placeholderTitle ?>" maxlength="100" placeholder="e.g. How can I help?">
                </div>
                <div class="col-md-5 mb-3">
                    <label for="placeholder_text" class="form-label">Placeholder Body</label>
                    <input type="text" class="form-control" id="placeholder_text" name="placeholder_text"
                           value="<?= $placeholderText ?>" maxlength="200" placeholder="Ask me anything!">
                </div>
            </div>

            <!-- ── Embed Code ──────────────────────────────────────── -->
            <div class="mb-0">
                <label class="form-label">Embed Code</label>
                <pre class="bg-dark text-light p-3 rounded" style="overflow-x: auto; font-size: 0.875rem;"><code id="embed-code-snippet" data-token="<?= htmlspecialchars($chatbot['widget_token'] ?? '') ?>" data-api-base="<?= htmlspecialchars(env('APP_URL', 'https://example.com')) ?>">&lt;script src=&quot;<?= htmlspecialchars((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost')) ?>/widget.js&quot;
        data-widget-token=&quot;<?= htmlspecialchars($chatbot['widget_token'] ?? '') ?>&quot;
        data-api-base=&quot;<?= htmlspecialchars(env('APP_URL', 'https://example.com')) ?>&quot;
        data-bot-name=&quot;<?= htmlspecialchars($styling['bot_name'] ?? 'Assistant') ?>&quot;
        data-primary-color=&quot;<?= htmlspecialchars($styling['primary_color'] ?? '#0d6efd') ?>&quot;
        data-header-text-color=&quot;<?= htmlspecialchars($styling['header_text_color'] ?? '#ffffff') ?>&quot;
        data-widget-theme=&quot;<?= htmlspecialchars($styling['panel_theme'] ?? 'light') ?>&quot;
        data-position=&quot;<?= htmlspecialchars($styling['position'] ?? 'bottom-right') ?>&quot;&gt;
&lt;/script&gt;</code></pre>
                <button type="button" class="btn btn-sm btn-outline-secondary mt-1" onclick="copyEmbedCode()">Copy Embed Code</button>
                <div class="form-text">Embed this snippet in any website to add this chatbot. Changes above update the code live.</div>
            </div>

            <!-- ── Allowed Domains (CORS) ──────────────────────────── -->
            <div class="mb-3">
                <label for="allowed_domains" class="form-label">Allowed Domains &mdash; CORS Restriction</label>
                <textarea class="form-control font-monospace" id="allowed_domains" name="allowed_domains" rows="3"
                          placeholder="example.com&#10;www.example.com"><?= htmlspecialchars($chatbot['allowed_domains'] ?? '') ?></textarea>
                <div class="form-text my-2">
                    <strong>Why is this needed?</strong> Without domain restrictions, anyone can copy your embed code
                    onto their own website and use your chatbot &mdash; consuming your token budget and exposing your
                    chatbot to unauthorized visitors. By listing only the domains you own, the server will reject requests
                    that come from any other origin.
                </div>
                <div class="form-text">
                    Enter one domain per line. You can paste full URLs &mdash; protocols, paths,
                    and default ports (80/443) are automatically stripped.
                    You may include port numbers for non-standard ports (e.g., <code>localhost:3000</code> for local development).
                    A bare domain (no port) matches <strong>any port</strong> on that host &mdash;
                    <code>localhost</code> allows <code>http://localhost:3000</code>, <code>http://localhost:8080</code>, etc.
                    Use <code>example.com:*</code> to allow any port on that domain.
                </div>
                <div class="form-text my-2">
                    <strong>Content Security Policy (CSP) note:</strong> For your own website, you also need to set a
                    Content-Security-Policy header that allows the chatbot scripts and connections. Add this to your
                    site's <code>&lt;head&gt;</code> or server config:
                </div>
                <pre class="bg-dark text-light p-3 rounded mt-1" style="overflow-x: auto; font-size: 0.8rem;">&lt;meta http-equiv=&quot;Content-Security-Policy&quot; content=&quot;
    script-src 'self' <?= htmlspecialchars(env('APP_URL', 'https://example.com')) ?>;
    connect-src 'self' <?= htmlspecialchars(env('APP_URL', 'https://example.com')) ?>;
    frame-src 'self' <?= htmlspecialchars(env('APP_URL', 'https://example.com')) ?>;
    style-src 'self' 'unsafe-inline';
&quot;&gt;</pre>
                <div class="form-text">
                    Replace <code><?= htmlspecialchars(env('APP_URL', 'https://example.com')) ?></code> with your
                    actual server URL (the same as <code>data-api-base</code> in the embed code). The CSP prevents
                    unauthorized scripts from running on your visitors&rsquo; browsers and blocks data exfiltration.
                </div>
            </div>
        </div>
    </div>

        <!-- Cost & Abuse Protection -->
        <div class="card mb-4">
            <div class="card-header">
                <h2 class="h5">Cost &amp; Abuse Protection</h2>
            </div>
            <div class="card-body">
				<p>Setting a sensible budget will limit the cost you incur from a visitor abusing your chatbot.</p>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="daily_token_budget" class="form-label">Daily Token Budget</label>
                        <input type="number" class="form-control" id="daily_token_budget" name="daily_token_budget"
                               value="<?= $dailyTokenBudget ?>" min="0" max="999999999" step="1">
                        <div class="form-text">
                            Total amount of conversation the bot can have each day. <strong>100,000</strong>
                            is plenty for most businesses. Enter <strong>0</strong> for no limit.
                        </div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="rate_limit_per_session" class="form-label">Rate Limit (per session / minute)</label>
                        <input type="number" class="form-control" id="rate_limit_per_session" name="rate_limit_per_session"
                               value="<?= $rateLimitPerSession ?>" min="0" max="9999" step="1">
                        <div class="form-text">
                            How many messages one visitor can send per minute. <strong>30</strong>
                            is enough for normal use. Enter <strong>0</strong> for no limit.
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="max_message_length" class="form-label">Max Message Length (characters)</label>
                        <input type="number" class="form-control" id="max_message_length" name="max_message_length"
                               value="<?= $maxMessageLength ?>" min="0" max="100000" step="1">
                        <div class="form-text">
                            Reject messages longer than this many characters (including spaces). Leave blank for no limit.
                            Set to e.g. <strong>2000</strong> to prevent token-wasting spam.
                        </div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="max_messages_per_conversation" class="form-label">Max Messages Per Conversation</label>
                        <input type="number" class="form-control" id="max_messages_per_conversation" name="max_messages_per_conversation"
                               value="<?= $maxMessagesPerConv ?>" min="0" max="9999" step="1">
                        <div class="form-text">
                            Maximum number of messages a single conversation can hold. Leave blank for no limit.
                            Set to e.g. <strong>50</strong> to cap long-running sessions.
                        </div>
                    </div>
                </div>
                <div class="row" id="cost-analysis-row">
                    <div class="col-12">
                        <div class="alert alert-info py-2 px-3 mb-0" id="cost-estimate" role="alert">
                            Select a model and set a daily budget above to see estimated max cost.
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary"><?= $submit ?></button>
            <a href="/chatbots" class="btn btn-outline-secondary">Cancel</a>
        </div>
    </form>

    <?php if ($isEdit): ?>
    <!-- Widget Preview (fixed position, not tied to any card) -->
    <div id="widget-preview">
        <div id="preview-panel" class="preview-panel">
            <div class="preview-header">
                <span id="preview-bot-name"><?= htmlspecialchars($styling['bot_name'] ?? 'Assistant') ?></span>
                <button class="preview-close" id="preview-close-btn" type="button">&times;</button>
            </div>
            <div class="preview-body">
                <div class="preview-placeholder">Ask me anything!</div>
            </div>
            <div class="preview-footer">
                <input type="text" placeholder="Type a message..." disabled>
                <button class="preview-send" tabindex="-1" type="button">
                    <svg viewBox="0 0 24 24" fill="#fff"><path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"/></svg>
                </button>
            </div>
        </div>
        <button class="preview-bubble" id="preview-bubble-btn" type="button">
            <svg viewBox="0 0 24 24" fill="#fff"><path d="M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm0 14H6l-2 2V4h16v12z"/></svg>
        </button>
    </div>
    <?php endif; ?>
</div>

<script>
    // ── Widget theme selector ───────────────────────────────────────────
    const themeSelect      = document.getElementById('widget_theme');
    const primaryInput     = document.getElementById('primary_color');
    const gradientInput    = document.getElementById('header_gradient_to');
    const accentInput      = document.getElementById('accent_color');
    const headerTextInput  = document.getElementById('header_text_color');
    const themeDesc        = document.getElementById('theme-desc');

    function applyTheme() {
        const opt = themeSelect?.options[themeSelect.selectedIndex];
        if (!opt || !opt.value) return;

        // Read data attributes from preset
        var primary   = opt.getAttribute('data-primary');
        var gradient  = opt.getAttribute('data-gradient-to');
        var accent    = opt.getAttribute('data-accent');
        var hdrText   = opt.getAttribute('data-header-text');

        // Apply to color pickers
        primaryInput.value     = primaryInput.value !== primary ? primary : primaryInput.value;
        gradientInput.value    = gradient || primary;
        accentInput.value      = accent || primary;
        headerTextInput.value  = hdrText || '#ffffff';

        // Update swatches
        document.getElementById('color-preview').style.backgroundColor        = primary;
        document.getElementById('gradient-preview').style.backgroundColor     = gradient || primary;
        document.getElementById('accent-preview').style.backgroundColor       = accent || primary;
        document.getElementById('header-text-preview').style.backgroundColor  = headerTextInput.value;

        if (themeDesc) {
            themeDesc.textContent = opt.textContent;
        }
        updateWidgetPreview();
    }

    themeSelect?.addEventListener('change', applyTheme);

    // When user manually changes a color, reset theme selector to Custom
    function resetTheme() {
        if (themeSelect) themeSelect.value = '';
        const customOpt = themeSelect?.querySelector('option[value=""]');
        if (themeDesc && customOpt) themeDesc.textContent = customOpt.textContent;
    }

    // Live preview of color pickers
    ['primary_color', 'header_gradient_to', 'accent_color', 'header_text_color'].forEach(function (id) {
        document.getElementById(id)?.addEventListener('input', function () {
            // Update corresponding swatch
            var swatchId = id === 'primary_color' ? 'color-preview'
                : id === 'header_gradient_to' ? 'gradient-preview'
                : id === 'accent_color' ? 'accent-preview'
                : 'header-text-preview';
            var swatch = document.getElementById(swatchId);
            if (swatch) swatch.style.backgroundColor = this.value;
            resetTheme();
            updateWidgetPreview();
        });
    });

    // ── Industry → system_prompt auto-fill ────────────────────────────────
    const industrySelect = document.getElementById('industry');
    const promptTextarea = document.getElementById('system_prompt');
    const promptSource   = document.getElementById('prompt-source');

    function updatePromptFromIndustry() {
        const selected = industrySelect.options[industrySelect.selectedIndex];
        if (!selected) return;

        const prompt = selected.getAttribute('data-prompt') || '';
        const category = selected.getAttribute('data-category') || '';
        const label = selected.textContent.trim();

        if (selected.value === '' || selected.value === '__custom__') {
            // Don't overwrite — user picked Custom or placeholder
            promptSource.textContent = selected.value === '__custom__'
                ? '(custom prompt — write whatever you like)'
                : '';
            return;
        }

        // Only fill if prompt is non-empty
        if (prompt) {
            promptTextarea.value = prompt;
            promptSource.textContent = 'Template: ' + label + ' · ' + category;
        }
    }

    industrySelect?.addEventListener('change', updatePromptFromIndustry);

    // On page load, show source for the pre-selected option
    if (industrySelect) {
        // Trigger once on load for edit mode
        setTimeout(updatePromptFromIndustry, 50);
    }

    function copyEmbedCode() {
        const code = document.getElementById('embed-code-snippet');
        if (!code) return;
        navigator.clipboard.writeText(code.textContent).then(() => {
            const btn = document.querySelector('button[onclick="copyEmbedCode()"]');
            if (btn) { btn.textContent = 'Copied!'; setTimeout(() => { btn.textContent = 'Copy Embed Code'; }, 2000); }
        }).catch(() => {});
    }

    // ── Live Embed Code Update ──────────────────────────────────────────
    function updateEmbedCode() {
        const codeEl = document.getElementById('embed-code-snippet');
        if (!codeEl) return;
        const primary    = document.getElementById('primary_color')?.value || '#0d6efd';
        const gradient   = document.getElementById('header_gradient_to')?.value || '';
        const accent     = document.getElementById('accent_color')?.value || '';
        const headerText = document.getElementById('header_text_color')?.value || '#ffffff';
        const position   = document.getElementById('position')?.value || 'bottom-right';
        const botName    = document.getElementById('bot_name')?.value || 'Assistant';
        const token      = codeEl.getAttribute('data-token') || '';
        const apiBase    = codeEl.getAttribute('data-api-base') || window.location.origin;
        const baseUrl    = window.location.origin;
        const panelTheme = document.querySelector('input[name="panel_theme"]:checked')?.value || 'light';
        const headerIcon = document.getElementById('header_icon')?.value || '';
        const headerSub  = document.getElementById('header_subtitle')?.value || '';
        const plIcon     = document.getElementById('placeholder_icon')?.value || '';
        const plTitle    = document.getElementById('placeholder_title')?.value || '';
        const plText     = document.getElementById('placeholder_text')?.value || '';

        var attrs = '        data-widget-token="' + token + '"\n' +
            '        data-api-base="' + apiBase + '"\n' +
            '        data-bot-name="' + botName + '"\n' +
            '        data-primary-color="' + primary + '"\n' +
            '        data-header-text-color="' + headerText + '"\n';
        if (gradient && gradient !== primary) {
            attrs += '        data-header-gradient-to="' + gradient + '"\n';
        }
        if (accent && accent !== primary) {
            attrs += '        data-accent-color="' + accent + '"\n';
        }
        if (headerIcon) {
            attrs += '        data-header-icon="' + headerIcon + '"\n';
        }
        if (headerSub) {
            attrs += '        data-header-subtitle="' + headerSub + '"\n';
        }
        if (plIcon) {
            attrs += '        data-placeholder-icon="' + plIcon + '"\n';
        }
        if (plTitle) {
            attrs += '        data-placeholder-title="' + plTitle + '"\n';
        }
        if (plText && plText !== 'Ask me anything!') {
            attrs += '        data-placeholder-text="' + plText + '"\n';
        }
        attrs += '        data-widget-theme="' + panelTheme + '"\n' +
            '        data-position="' + position + '">\n';

        codeEl.textContent = '<script src="' + baseUrl + '/widget.js"\n' + attrs + '<' + '/script>';
    }

    // ── Widget Live Preview ───────────────────────────────────────────────
    function updateWidgetPreview() {
        const primary    = document.getElementById('primary_color')?.value || '#0d6efd';
        const gradient   = document.getElementById('header_gradient_to')?.value || '';
        const accent     = document.getElementById('accent_color')?.value || '';
        const headerText = document.getElementById('header_text_color')?.value || '#ffffff';
        const position   = document.getElementById('position')?.value || 'bottom-right';
        const botName    = document.getElementById('bot_name')?.value || 'Assistant';
        const panelTheme = document.querySelector('input[name="panel_theme"]:checked')?.value || 'light';
        const isDark     = panelTheme === 'dark';
        const headerIcon = document.getElementById('header_icon')?.value || '';
        const headerSub  = document.getElementById('header_subtitle')?.value || '';
        const plIcon     = document.getElementById('placeholder_icon')?.value || '';
        const plTitle    = document.getElementById('placeholder_title')?.value || '';
        const plText     = document.getElementById('placeholder_text')?.value || '';

        // Header gradient
        var headerBg = gradient && gradient !== primary
            ? 'linear-gradient(135deg, ' + primary + ', ' + gradient + ')'
            : primary;

        document.documentElement.style.setProperty('--widget-primary', primary);
        document.documentElement.style.setProperty('--widget-accent', accent || primary);
        document.documentElement.style.setProperty('--widget-header-bg', headerBg);
        document.documentElement.style.setProperty('--widget-header-text', headerText);
        document.documentElement.style.setProperty('--widget-right', position === 'bottom-right' ? '20px' : 'auto');
        document.documentElement.style.setProperty('--widget-left', position === 'bottom-left' ? '20px' : 'auto');

        // Panel theme
        document.documentElement.style.setProperty('--widget-surface', isDark ? '#111d35' : '#ffffff');
        document.documentElement.style.setProperty('--widget-body-bg', isDark ? '#0a1628' : '#f5f7fa');
        document.documentElement.style.setProperty('--widget-text-secondary', isDark ? '#5a6d8a' : '#8899b4');
        document.documentElement.style.setProperty('--widget-border-color', isDark ? 'rgba(255,255,255,0.08)' : 'rgba(0,0,0,0.08)');
        document.documentElement.style.setProperty('--widget-surface-2', isDark ? 'rgba(255,255,255,0.04)' : '#f0f2f5');
        document.documentElement.style.setProperty('--widget-placeholder-title', isDark ? '#d0d8e0' : '#2c3e50');

        // Update header
        const headerEl = document.querySelector('.preview-header');
        if (headerEl) {
            var headerHtml = '<div class="preview-header-left">';
            if (headerIcon) {
                headerHtml += '<span class="preview-header-icon">' + headerIcon + '</span>';
            }
            headerHtml += '<div class="preview-header-text">';
            headerHtml += '<span class="preview-header-title" id="preview-bot-name">' + botName + '</span>';
            if (headerSub) {
                headerHtml += '<span class="preview-header-subtitle">' + headerSub + '</span>';
            }
            headerHtml += '</div></div>';
            headerHtml += '<button class="preview-close" id="preview-close-btn" type="button">&times;</button>';
            headerEl.innerHTML = headerHtml;
        }

        // Update placeholder
        const bodyEl = document.querySelector('.preview-body');
        if (bodyEl) {
            var phHtml = '<div class="preview-placeholder">';
            if (plIcon) {
                phHtml += '<div class="preview-placeholder-icon">' + plIcon + '</div>';
            }
            if (plTitle) {
                phHtml += '<div class="preview-placeholder-title">' + plTitle + '</div>';
            }
            phHtml += '<div class="preview-placeholder-text">' + plText + '</div>';
            phHtml += '</div>';
            bodyEl.innerHTML = phHtml;
        }

        updateEmbedCode();
    }

    // Live update on any styling field change
    document.getElementById('position')?.addEventListener('change', updateWidgetPreview);
    document.getElementById('bot_name')?.addEventListener('input', updateWidgetPreview);
    document.getElementById('header_icon')?.addEventListener('change', updateWidgetPreview);
    document.getElementById('header_subtitle')?.addEventListener('input', updateWidgetPreview);
    document.getElementById('placeholder_icon')?.addEventListener('change', updateWidgetPreview);
    document.getElementById('placeholder_title')?.addEventListener('input', updateWidgetPreview);
    document.getElementById('placeholder_text')?.addEventListener('input', updateWidgetPreview);
    document.querySelectorAll('input[name="panel_theme"]').forEach(function (el) {
        el.addEventListener('change', function () {
            // When panel theme changes, also reset theme selector to Custom
            if (themeSelect) themeSelect.value = '';
            updateWidgetPreview();
        });
    });

    // Preview bubble toggle (uses delegation since header may be re-rendered)
    const previewBubble = document.getElementById('preview-bubble-btn');
    const previewPanel = document.getElementById('preview-panel');

    previewBubble?.addEventListener('click', function () {
        previewPanel?.classList.add('open');
        previewBubble.style.display = 'none';
    });
    previewPanel?.addEventListener('click', function (e) {
        if (e.target && e.target.getAttribute('id') === 'preview-close-btn') {
            previewPanel.classList.remove('open');
            if (previewBubble) previewBubble.style.display = 'flex';
        }
    });

    // Init preview on load
    setTimeout(updateWidgetPreview, 100);

    // ── Collapsible toggle (no Bootstrap JS needed) ───────────────────
    function toggleCollapse(header) {
        const targetId = header.getAttribute('data-bs-target');
        if (!targetId) return;
        const body = document.querySelector(targetId);
        if (!body) return;
        const isOpen = body.classList.toggle('show');
        header.classList.toggle('collapsed', !isOpen);
        header.setAttribute('aria-expanded', isOpen);
    }
    // ── Cost estimate: recalculate when model or daily budget changes ──────
    // Pricing: cost per 1M tokens (input, output).
    // to account for real-world usage patterns.
    // Updated July 13, 2026
    var MODEL_PRICING = {
        'gpt-4.1-mini':  [0.40, 1.60],
        'gpt-4.1':       [2.00, 8.00],
        'gpt-5-nano':    [0.05, 0.40],
        'gpt-5-mini':    [0.25, 2.00],
        'gpt-5':         [1.25, 10.00],
        'gpt-5-pro':     [15.00, 120.00],
        'gpt-5.1':       [1.25, 10.00],
        'gpt-5.2':       [1.75, 14.00],
        'gpt-5.2-pro':   [21.00, 168.00],
        'gpt-5.3-codex': [1.75, 14.00],
        'gpt-5.4-nano':  [0.20, 1.25],
        'gpt-5.4-mini':  [0.75, 4.50],
        'gpt-5.4':       [2.50, 15.00],
        'gpt-5.4-pro':   [30.00, 180.00],
        'gpt-5.5':       [5.00, 30.00],
        'gpt-5.5-pro':   [30.00, 180.00],
        'gpt-5.6-luna':  [1.00, 6.00],
        'gpt-5.6-terra': [2.50, 15.00],
        'gpt-5.6-sol':   [5.00, 30.00],
    };

    // Strip non-digit characters from number inputs (handles pasted spaces, etc.)
    function sanitizeNumericInput(e) {
        this.value = this.value.replace(/[^0-9]/g, '');
    }
    document.getElementById('daily_token_budget')?.addEventListener('input', sanitizeNumericInput);
    document.getElementById('rate_limit_per_session')?.addEventListener('input', sanitizeNumericInput);

    function updateCostEstimate() {
        var modelSelect = document.getElementById('model');
        var budgetInput = document.getElementById('daily_token_budget');
        var estimateEl  = document.getElementById('cost-estimate');
        if (!modelSelect || !budgetInput || !estimateEl) return;

        var model   = modelSelect.value;
        var raw     = budgetInput.value.trim();
        var budget  = parseInt(raw, 10);

        // Unlimited: show a simple note
        if (raw === '' || raw === '0' || isNaN(budget) || budget <= 0) {
            estimateEl.className = 'alert alert-success py-2 px-3 mb-0';
            estimateEl.innerHTML = '✅ <strong>Unlimited</strong> — no cap on daily cost.';
            return;
        }

        var prices = MODEL_PRICING[model];
        if (!prices) {
            estimateEl.className = 'alert alert-warning py-2 px-3 mb-0';
            estimateEl.innerHTML = '⚠️ Pricing not available for <strong>' + model +
                '</strong>. Set a budget and monitor usage.';
            return;
        }

        // Blended rate: real-world mix of input vs output tokens
        var blendedPer1M = prices[0] * 0.3 + prices[1] * 0.7;
        var cost = budget / 1000000 * blendedPer1M;

        // Format nicely
        var modelLabel = modelSelect.options[modelSelect.selectedIndex].text;
        var costStr = cost < 0.01 ? 'less than $0.01' : '$' + cost.toFixed(2);
        var budgetStr = budget.toLocaleString();

        estimateEl.className = 'alert alert-info py-2 px-3 mb-0';
        estimateEl.innerHTML = 'At <strong>' + budgetStr + '</strong> tokens/day with <strong>' +
            modelLabel + '</strong>, you\'d spend about <strong>' + costStr +
            '</strong> per day at most.';
    }

    document.getElementById('model')?.addEventListener('change', updateCostEstimate);
    document.getElementById('daily_token_budget')?.addEventListener('input', updateCostEstimate);
    // Run once on page load so the estimate is current
    updateCostEstimate();
</script>
<?php
$pageContent = ob_get_clean();
require __DIR__ . '/../dashboard/layout.php';
