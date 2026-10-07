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
if (empty($pageTitle)) {
    $pageTitle = ($isEdit ? 'Edit Chatbot' : 'Create Chatbot') . ' - ' . ($user['brand_name'] ?? 'Chatbot Assistant');
}
$submit   = $isEdit ? 'Save changes' : 'Create chatbot';

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
$defaultConfig = ['temperature' => 0.0, 'max_tokens' => 1024, 'model' => 'gpt-4.1-mini'];
$modelConfig   = $isEdit && isset($chatbot['model_config']) && is_array($chatbot['model_config'])
    ? $chatbot['model_config'] : $defaultConfig;
$temperature = htmlspecialchars((string) ($modelConfig['temperature'] ?? 0.0));
$maxTokens   = htmlspecialchars((string) ($modelConfig['max_tokens'] ?? 1024));
$model       = htmlspecialchars($modelConfig['model'] ?? 'gpt-4.1-mini');

// The model dropdown lists ONLY the account's configured LLM models
// (Settings → Model management), exactly like the JS settings-form: a
// "Select a model…" placeholder plus one option per configured entry. There
// is no static OpenAI fallback list — that was deprecated with the
// multi-model configuration.
$configuredModels = \App\Model\Admin::decodeLlmModels(
    \App\Model\Admin::getProviderSettings((int) $user['id'])['llm_models'] ?? null
);
// Keep the stored selection selectable even if that model entry was renamed
// or removed since it was chosen, so the value round-trips (the JS port
// handles this the same way — mc.model may not be in the current list).
$knownNames = array_map(fn($m) => (string) ($m['name'] ?? ''), $configuredModels);
$storedModelRaw = (string) ($modelConfig['model'] ?? '');

// Retrieval strategy
$retrievalStrategy = $old['retrieval_strategy'] ?? ($isEdit ? ($chatbot['retrieval_strategy'] ?? 'traditional_rag') : 'traditional_rag');

// ── Widget theme presets ──────────────────────────────────────────────────
$widgetThemes = [
    ''               => ['label' => '✏️ Custom', 'primary' => '#0d6efd', 'gradient_to' => '', 'accent' => '', 'header_text' => '#ffffff', 'desc' => 'Choose your own colors below'],
    'default-blue'   => ['label' => '🔵 Default Blue',  'primary' => '#0d6efd', 'gradient_to' => '', 'accent' => '', 'header_text' => '#ffffff', 'desc' => 'Clean, classic blue — professional and familiar'],
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

?>
<?php ob_start(); ?>
<?php if ($success): ?>
    <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
<?php endif; ?>
<?php if (is_array($errors)): ?>
    <div class="alert alert-error">
        <ul style="margin:0;padding-left:1.25rem;">
            <?php foreach ($errors as $err): ?>
                <li><?= htmlspecialchars($err) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<form method="POST" action="<?= $action ?>">
    <input type="hidden" name="_csrf" value="<?= \App\Auth\Session::csrfToken() ?>">
    <?php if ($isEdit): ?>
        <input type="hidden" name="_method" value="PUT">
    <?php endif; ?>

    <div class="card">
        <h2>Basics</h2>
        <div class="form-row">
            <div class="field">
                <label class="label" for="name">Chatbot name <span style="color:var(--danger);">*</span></label>
                <input type="text" class="input" id="name" name="name" value="<?= $name ?>" required maxlength="255">
            </div>
            <?php if ($isEdit): ?>
            <div class="field">
                <label class="label" for="status">Status</label>
                <select class="select" id="status" name="status">
                    <option value="active" <?= $currentStatus === 'active' ? 'selected' : '' ?>>Active</option>
                    <option value="paused" <?= $currentStatus === 'paused' ? 'selected' : '' ?>>Paused</option>
                </select>
            </div>
            <?php endif; ?>
            <div class="field">
                <label class="label" for="industry">Industry / prompt template</label>
                <select class="select" id="industry" name="industry">
                    <?php
                    $first = true;
                    foreach ($industryPresets as $category => $presets):
                        if ($first) {
                            $first = false;
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
                <div class="muted" style="font-size:0.8rem;">Select an industry to auto-fill the system prompt. Choose "Custom" to write your own.</div>
            </div>
        </div>
        <div class="field">
            <label class="label" for="system_prompt">System prompt</label>
            <textarea class="textarea" id="system_prompt" name="system_prompt" rows="5"
                      placeholder="You are a helpful assistant for [company]. Answer questions based on the provided documents."><?= $systemPrompt ?></textarea>
            <div class="muted" style="font-size:0.8rem;">
                Instructions that define your chatbot's personality and behavior.
                <span id="prompt-source"></span>
            </div>
        </div>
    </div>

    <div class="card">
        <h2>Model</h2>
        <div class="form-row">
            <div class="field">
                <label class="label" for="model">Model</label>
                <select class="select" id="model" name="model">
                    <option value="">Select a model…</option>
                    <?php foreach ($configuredModels as $cm): ?>
                        <option value="<?= htmlspecialchars($cm['name']) ?>" <?= ($storedModelRaw === (string) $cm['name']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($cm['name']) ?><?= !empty($cm['model']) ? ' (' . htmlspecialchars($cm['model']) . ')' : '' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <div class="muted" style="font-size:0.8rem;">
                    <?php if ($configuredModels === []): ?>
                        No models configured yet. Add models with their providers in <a href="/settings">Settings → Model management</a> first.
                    <?php else: ?>
                        Choose from the models in Settings; the others act as failover.
                    <?php endif; ?>
                </div>
            </div>
            <div class="field">
                <label class="label" for="temperature">Temperature</label>
                <input type="number" class="input" id="temperature" name="temperature"
                       value="<?= $temperature ?>" min="0" max="2" step="0.1">
                <div class="muted" style="font-size:0.8rem;">0 = deterministic, 2 = very creative.</div>
            </div>
            <div class="field">
                <label class="label" for="max_tokens">Max tokens</label>
                <input type="number" class="input" id="max_tokens" name="max_tokens"
                       value="<?= $maxTokens ?>" min="64" max="16384" step="1">
                <div class="muted" style="font-size:0.8rem;">
                    Max response length. <strong>Short Q&amp;A:</strong> 256–512 &middot;
                    <strong>Default:</strong> 1024 &middot;
                    <strong>Long answers/code:</strong> 2048–4096 &middot;
                    <strong>Max output:</strong> 16384. Higher = more tokens billed.
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <h2>Lead capture</h2>
        <label style="display:inline-flex;align-items:center;gap:0.4rem;">
            <input type="checkbox" id="lead_capture_enabled" name="lead_capture_enabled" value="1"
                   <?= !empty($chatbot['lead_capture_enabled']) ? 'checked' : '' ?>>
            <strong>Enable lead capture</strong>
        </label>
        <div class="muted" style="font-size:0.8rem;margin-top:0.35rem;">
            When enabled, the chatbot will naturally ask visitors for their name, email address,
            and phone number during the conversation. Once all three are collected, they are
            stored as leads and viewable from the chatbot's management page.
        </div>
        <div class="muted" style="font-size:0.8rem;margin-top:0.35rem;">
            <strong>How it works:</strong> The AI asks for each piece of information one at a time.
            When it detects all three fields have been provided, the lead is automatically saved
            to this chatbot's lead list.
        </div>
    </div>

    <div class="card">
        <h2>Retrieval strategy</h2>
        <div class="field">
            <label class="label">Document retrieval method</label>
            <div class="row">
                <label style="display:inline-flex;align-items:center;gap:0.4rem;">
                    <input type="radio" name="retrieval_strategy" value="traditional_rag"
                           <?= ($retrievalStrategy ?? 'traditional_rag') === 'traditional_rag' ? 'checked' : '' ?>>
                    <strong>Traditional RAG</strong>
                </label>
                <label style="display:inline-flex;align-items:center;gap:0.4rem;">
                    <input type="radio" name="retrieval_strategy" value="page_index"
                           <?= ($retrievalStrategy ?? 'traditional_rag') === 'page_index' ? 'checked' : '' ?>>
                    <strong>PageIndex</strong>
                </label>
            </div>
            <div class="muted" style="font-size:0.8rem;margin-top:0.5rem;">
                <strong>Traditional RAG:</strong> Documents are chunked and embedded into a vector
                database. At query time, the chatbot uses embedding similarity search to find relevant
                passages. Best for most use cases &mdash; fast, proven, and accurate.
            </div>
            <div class="muted" style="font-size:0.8rem;margin-top:0.35rem;">
                <strong>PageIndex:</strong> Documents are parsed into a hierarchical table of contents
                structure. At query time, an LLM reasons over the outline to find the most relevant
                section &mdash; no embedding needed. Best for well-structured documents with clear
                headings and sections. Requires additional processing after document upload.
            </div>
            <div class="muted" style="font-size:0.8rem;margin-top:0.5rem;">
                Note: Changing the strategy after documents have been indexed will not automatically
                reprocess existing documents. You must re-upload and re-index documents after switching.
            </div>
        </div>
    </div>

    <div class="card">
        <h2>Widget appearance</h2>

        <div class="form-row">
            <div class="field">
                <label class="label" for="widget_theme">🎨 Theme preset</label>
                <select class="select" id="widget_theme" name="widget_theme">
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
                <div class="muted" style="font-size:0.8rem;" id="theme-desc">
                    <?= htmlspecialchars($widgetThemes[$widgetTheme]['desc'] ?? 'Choose a theme or set custom colors below') ?>
                </div>
            </div>
            <div class="field">
                <label class="label">Widget panel theme</label>
                <div class="row">
                    <label style="display:inline-flex;align-items:center;gap:0.4rem;">
                        <input type="radio" name="panel_theme" value="light" <?= ($styling['panel_theme'] ?? 'light') === 'light' ? 'checked' : '' ?>>
                        Light
                    </label>
                    <label style="display:inline-flex;align-items:center;gap:0.4rem;">
                        <input type="radio" name="panel_theme" value="dark" <?= ($styling['panel_theme'] ?? 'light') === 'dark' ? 'checked' : '' ?>>
                        Dark
                    </label>
                </div>
                <div class="muted" style="font-size:0.8rem;">Light panel is white/silver; dark panel is navy/charcoal. Presets look best with dark mode unless you pick a light-friendly header text color.</div>
            </div>
        </div>

        <div class="form-row">
            <div class="field">
                <label class="label" for="bot_name">Bot display name</label>
                <input type="text" class="input" id="bot_name" name="bot_name" value="<?= $botName ?>" maxlength="100">
            </div>
            <div class="field">
                <label class="label" for="primary_color">
                    Primary &nbsp;<span class="preview-color" id="color-preview" style="display:inline-block;width:1.25rem;height:1.25rem;border-radius:50%;vertical-align:middle;background-color: <?= $primaryColor ?>;"></span>
                </label>
                <input type="color" class="input" id="primary_color" name="primary_color" value="<?= $primaryColor ?>">
            </div>
            <div class="field">
                <label class="label" for="header_gradient_to">
                    Gradient &nbsp;<span class="preview-color" id="gradient-preview" style="display:inline-block;width:1.25rem;height:1.25rem;border-radius:50%;vertical-align:middle;background-color: <?= $headerGradientTo ?: $primaryColor ?>;"></span>
                </label>
                <input type="color" class="input" id="header_gradient_to" name="header_gradient_to" value="<?= $headerGradientTo ?: $primaryColor ?>">
                <div class="muted" style="font-size:0.8rem;">Leave same as Primary for solid header.</div>
            </div>
            <div class="field">
                <label class="label" for="accent_color">
                    Accent &nbsp;<span class="preview-color" id="accent-preview" style="display:inline-block;width:1.25rem;height:1.25rem;border-radius:50%;vertical-align:middle;background-color: <?= $accentColor ?: $primaryColor ?>;"></span>
                </label>
                <input type="color" class="input" id="accent_color" name="accent_color" value="<?= $accentColor ?: $primaryColor ?>">
                <div class="muted" style="font-size:0.8rem;">Send button, links, hover effects. Leave blank to match Primary.</div>
            </div>
            <div class="field">
                <label class="label" for="header_text_color">
                    Header text &nbsp;<span class="preview-color" id="header-text-preview" style="display:inline-block;width:1.25rem;height:1.25rem;border-radius:50%;vertical-align:middle;background-color: <?= $headerTextColor ?>;"></span>
                </label>
                <input type="color" class="input" id="header_text_color" name="header_text_color" value="<?= $headerTextColor ?>">
            </div>
        </div>

        <div class="form-row">
            <div class="field">
                <label class="label" for="header_icon">Header icon</label>
                <select class="select" id="header_icon" name="header_icon">
                    <?php foreach ($iconOptions as $val => $label): ?>
                    <option value="<?= htmlspecialchars($val) ?>" <?= $headerIcon === $val ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label class="label" for="header_subtitle">Header subtitle</label>
                <input type="text" class="input" id="header_subtitle" name="header_subtitle" value="<?= $headerSubtitle ?>" maxlength="100" placeholder="e.g. Online">
            </div>
            <div class="field">
                <label class="label" for="position">Widget position</label>
                <select class="select" id="position" name="position">
                    <option value="bottom-right" <?= $position === 'bottom-right' ? 'selected' : '' ?>>Bottom right</option>
                    <option value="bottom-left"  <?= $position === 'bottom-left' ? 'selected' : '' ?>>Bottom left</option>
                </select>
            </div>
        </div>

        <div class="form-row">
            <div class="field">
                <label class="label" for="placeholder_icon">Placeholder icon</label>
                <select class="select" id="placeholder_icon" name="placeholder_icon">
                    <?php foreach ($iconOptions as $val => $label): ?>
                    <option value="<?= htmlspecialchars($val) ?>" <?= $placeholderIcon === $val ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label class="label" for="placeholder_title">Placeholder title</label>
                <input type="text" class="input" id="placeholder_title" name="placeholder_title" value="<?= $placeholderTitle ?>" maxlength="100" placeholder="e.g. How can I help?">
            </div>
            <div class="field">
                <label class="label" for="placeholder_text">Placeholder body</label>
                <input type="text" class="input" id="placeholder_text" name="placeholder_text" value="<?= $placeholderText ?>" maxlength="200" placeholder="Ask me anything!">
            </div>
        </div>
    </div>

    <div class="card">
        <h2>Embed code</h2>
        <div class="field">
            <pre class="code-block"><code id="embed-code-snippet" data-token="<?= htmlspecialchars($chatbot['widget_token'] ?? '') ?>" data-api-base="<?= htmlspecialchars(env('APP_URL', 'https://example.com')) ?>">&lt;script src=&quot;<?= htmlspecialchars((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost')) ?>/widget.js&quot;
        data-widget-token=&quot;<?= htmlspecialchars($chatbot['widget_token'] ?? '') ?>&quot;
        data-api-base=&quot;<?= htmlspecialchars(env('APP_URL', 'https://example.com')) ?>&quot;
        data-bot-name=&quot;<?= htmlspecialchars($styling['bot_name'] ?? 'Assistant') ?>&quot;
        data-primary-color=&quot;<?= htmlspecialchars($styling['primary_color'] ?? '#0d6efd') ?>&quot;
        data-header-text-color=&quot;<?= htmlspecialchars($styling['header_text_color'] ?? '#ffffff') ?>&quot;
        data-widget-theme=&quot;<?= htmlspecialchars($styling['panel_theme'] ?? 'light') ?>&quot;
        data-position=&quot;<?= htmlspecialchars($styling['position'] ?? 'bottom-right') ?>&quot;&gt;
&lt;/script&gt;</code></pre>
            <div class="form-actions" style="margin-top:0.75rem;">
                <button type="button" class="btn" onclick="copyEmbedCode()">Copy embed code</button>
            </div>
            <div class="muted" style="font-size:0.8rem;">Embed this snippet in any website to add this chatbot. Changes above update the code live.</div>
        </div>
    </div>

    <div class="card">
        <h2>Domain security</h2>
        <div class="field">
            <label class="label" for="allowed_domains">Allowed domains &mdash; CORS restriction</label>
            <textarea class="textarea" id="allowed_domains" name="allowed_domains" rows="3"
                      placeholder="example.com&#10;www.example.com"><?= htmlspecialchars($chatbot['allowed_domains'] ?? '') ?></textarea>
            <div class="muted" style="font-size:0.8rem;margin-top:0.35rem;">
                <strong>Why is this needed?</strong> Without domain restrictions, anyone can copy your embed code
                onto their own website and use your chatbot &mdash; consuming your token budget and exposing your
                chatbot to unauthorized visitors. By listing only the domains you own, the server will reject requests
                that come from any other origin.
            </div>
            <div class="muted" style="font-size:0.8rem;margin-top:0.35rem;">
                Enter one domain per line. You can paste full URLs &mdash; protocols, paths,
                and default ports (80/443) are automatically stripped.
                You may include port numbers for non-standard ports (e.g., <code>localhost:3000</code> for local development).
                A bare domain (no port) matches <strong>any port</strong> on that host &mdash;
                <code>localhost</code> allows <code>http://localhost:3000</code>, <code>http://localhost:8080</code>, etc.
                Use <code>example.com:*</code> to allow any port on that domain.
            </div>
            <div class="muted" style="font-size:0.8rem;margin-top:0.35rem;">
                <strong>Content Security Policy (CSP) note:</strong> For your own website, you also need to set a
                Content-Security-Policy header that allows the chatbot scripts and connections. Add this to your
                site's <code>&lt;head&gt;</code> or server config:
            </div>
            <pre class="code-block" style="margin-top:0.35rem;">&lt;meta http-equiv=&quot;Content-Security-Policy&quot; content=&quot;
    script-src 'self' <?= htmlspecialchars(env('APP_URL', 'https://example.com')) ?>;
    connect-src 'self' <?= htmlspecialchars(env('APP_URL', 'https://example.com')) ?>;
    frame-src 'self' <?= htmlspecialchars(env('APP_URL', 'https://example.com')) ?>;
    style-src 'self' 'unsafe-inline';
&quot;&gt;</pre>
            <div class="muted" style="font-size:0.8rem;">
                Replace <code><?= htmlspecialchars(env('APP_URL', 'https://example.com')) ?></code> with your
                actual server URL (the same as <code>data-api-base</code> in the embed code). The CSP prevents
                unauthorized scripts from running on your visitors&rsquo; browsers and blocks data exfiltration.
            </div>
        </div>
    </div>

    <div class="card">
        <h2>Widget preview</h2>
        <p class="muted">
            A live preview of the widget floats at the bottom of this page, exactly where
            it will sit on your site. Edit the fields above and it updates; click the bubble
            to open the panel. Position follows the <em>Panel position</em> setting.
        </p>
    </div>

    <div class="card">
        <h2>Anti-abuse guardrails</h2>
        <p class="muted">Setting a sensible budget will limit the cost you incur from a visitor abusing your chatbot.</p>
        <div class="form-row">
            <div class="field">
                <label class="label" for="daily_token_budget">Daily token budget</label>
                <input type="number" class="input" id="daily_token_budget" name="daily_token_budget"
                       value="<?= $dailyTokenBudget ?>" min="0" max="999999999" step="1">
                <div class="muted" style="font-size:0.8rem;">
                    Total amount of conversation the bot can have each day. <strong>100,000</strong>
                    is plenty for most businesses. Enter <strong>0</strong> for no limit.
                </div>
            </div>
            <div class="field">
                <label class="label" for="rate_limit_per_session">Rate limit (per session / minute)</label>
                <input type="number" class="input" id="rate_limit_per_session" name="rate_limit_per_session"
                       value="<?= $rateLimitPerSession ?>" min="0" max="9999" step="1">
                <div class="muted" style="font-size:0.8rem;">
                    How many messages one visitor can send per minute. <strong>30</strong>
                    is enough for normal use. Enter <strong>0</strong> for no limit.
                </div>
            </div>
        </div>
        <div class="form-row">
            <div class="field">
                <label class="label" for="max_message_length">Max message length (characters)</label>
                <input type="number" class="input" id="max_message_length" name="max_message_length"
                       value="<?= $maxMessageLength ?>" min="0" max="100000" step="1">
                <div class="muted" style="font-size:0.8rem;">
                    Reject messages longer than this many characters (including spaces). Leave blank for no limit.
                    Set to e.g. <strong>2000</strong> to prevent token-wasting spam.
                </div>
            </div>
            <div class="field">
                <label class="label" for="max_messages_per_conversation">Max messages per conversation</label>
                <input type="number" class="input" id="max_messages_per_conversation" name="max_messages_per_conversation"
                       value="<?= $maxMessagesPerConv ?>" min="0" max="9999" step="1">
                <div class="muted" style="font-size:0.8rem;">
                    Maximum number of messages a single conversation can hold. Leave blank for no limit.
                    Set to e.g. <strong>50</strong> to cap long-running sessions.
                </div>
            </div>
        </div>
    </div>

    <div class="form-actions">
        <button type="submit" class="btn btn-primary"><?= $submit ?></button>
        <a href="/chatbots" class="btn">Cancel</a>
    </div>
</form>

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

        var primary   = opt.getAttribute('data-primary');
        var gradient  = opt.getAttribute('data-gradient-to');
        var accent    = opt.getAttribute('data-accent');
        var hdrText   = opt.getAttribute('data-header-text');

        primaryInput.value     = primaryInput.value !== primary ? primary : primaryInput.value;
        gradientInput.value    = gradient || primary;
        accentInput.value      = accent || primary;
        headerTextInput.value  = hdrText || '#ffffff';

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

    function resetTheme() {
        if (themeSelect) themeSelect.value = '';
        const customOpt = themeSelect?.querySelector('option[value=""]');
        if (themeDesc && customOpt) themeDesc.textContent = customOpt.textContent;
    }

    ['primary_color', 'header_gradient_to', 'accent_color', 'header_text_color'].forEach(function (id) {
        document.getElementById(id)?.addEventListener('input', function () {
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
            promptSource.textContent = selected.value === '__custom__'
                ? '(custom prompt — write whatever you like)'
                : '';
            return;
        }

        if (prompt) {
            promptTextarea.value = prompt;
            promptSource.textContent = 'Template: ' + label + ' · ' + category;
        }
    }

    industrySelect?.addEventListener('change', updatePromptFromIndustry);

    if (industrySelect) {
        setTimeout(updatePromptFromIndustry, 50);
    }

    function copyEmbedCode() {
        const code = document.getElementById('embed-code-snippet');
        if (!code) return;
        navigator.clipboard.writeText(code.textContent).then(() => {
            const btn = document.querySelector('button[onclick="copyEmbedCode()"]');
            if (btn) { btn.textContent = 'Copied!'; setTimeout(() => { btn.textContent = 'Copy embed code'; }, 2000); }
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

    // The floating preview lives in its own script (rendered before </body>).
    // This alias just refreshes the embed code on the same input events.
    function updateWidgetPreview() {
        updateEmbedCode();
    }

    document.getElementById('position')?.addEventListener('change', updateWidgetPreview);
    document.getElementById('bot_name')?.addEventListener('input', updateWidgetPreview);
    document.getElementById('header_icon')?.addEventListener('change', updateWidgetPreview);
    document.getElementById('header_subtitle')?.addEventListener('input', updateWidgetPreview);
    document.getElementById('placeholder_icon')?.addEventListener('change', updateWidgetPreview);
    document.getElementById('placeholder_title')?.addEventListener('input', updateWidgetPreview);
    document.getElementById('placeholder_text')?.addEventListener('input', updateWidgetPreview);
    document.querySelectorAll('input[name="panel_theme"]').forEach(function (el) {
        el.addEventListener('change', function () {
            if (themeSelect) themeSelect.value = '';
            updateWidgetPreview();
        });
    });

    setTimeout(updateWidgetPreview, 100);

    // Cost estimation per model was removed: models are arbitrary
    // OpenAI-compatible providers now, so there is no fixed price map. The
    // daily token budget above still caps spend absolutely.
</script>
<?php
$pageContent = ob_get_clean();

// ── Floating widget preview (port of the JS WidgetPreview component) ────────
// Rendered AFTER $pageContent so it lives outside the form, fixed to the
// viewport bottom corner the same way the real widget sits on a site.
$previewColor = static fn(?string $c): string => htmlspecialchars((string) ($c ?? ''), ENT_QUOTES, 'UTF-8');
ob_start();
?>
<div id="widget-preview-root" aria-live="polite">
    <!-- Panel (hidden until the bubble is clicked) -->
    <div id="wp-panel" role="dialog" aria-label="Widget preview panel" hidden
         style="display:none; position:fixed; z-index:9999; bottom:96px; width:340px; max-width:calc(100vw - 40px);
                border-radius:16px; overflow:hidden; box-shadow:0 8px 32px rgba(0,0,0,0.35);
                background:#ffffff; border:1px solid rgba(0,0,0,0.08);
                <?= $styling['position'] ?? 'bottom-right' ?>:20px;">
        <div id="wp-header" style="padding:12px 16px; display:flex; align-items:center; justify-content:space-between; gap:10px; border-bottom:3px solid transparent;">
            <div style="display:flex; align-items:center; gap:10px; min-width:0; flex:1;">
                <span id="wp-header-icon" style="display:none; width:36px; height:36px; border-radius:50%; background:rgba(0,0,0,0.2); align-items:center; justify-content:center; font-size:18px; flex-shrink:0;"></span>
                <div style="display:flex; flex-direction:column; min-width:0;">
                    <span id="wp-bot-name" style="font-weight:600; font-size:15px; line-height:1.3;"></span>
                    <span id="wp-header-subtitle" style="font-size:11px; opacity:0.8; line-height:1.3; display:none;"></span>
                </div>
            </div>
            <button type="button" id="wp-close" aria-label="Close preview"
                    style="background:none; border:none; cursor:pointer; font-size:20px; opacity:0.85;">&times;</button>
        </div>
        <div id="wp-body" style="padding:18px; display:flex; flex-direction:column; align-items:center; text-align:center; gap:4px; min-height:400px; background:#f5f7fa;">
            <div id="wp-placeholder-icon" style="display:none; font-size:30px; line-height:1;"></div>
            <div id="wp-placeholder-title" style="display:none; font-weight:600; font-size:15px; color:#2c3e50;"></div>
            <div id="wp-placeholder-text" style="font-size:13px; color:#4a5568;">Ask me anything!</div>
        </div>
        <div id="wp-footer" style="display:flex; padding:10px; gap:8px; border-top:1px solid rgba(0,0,0,0.08); background:#ffffff;">
            <input id="wp-input" type="text" disabled placeholder="Type your message…"
                   style="flex:1; padding:8px 12px; border:1px solid rgba(0,0,0,0.15); border-radius:8px; font-size:13px; background:#fff; color:var(--text);">
            <span id="wp-send" style="display:flex; align-items:center; justify-content:center; width:36px; height:36px; border-radius:8px; flex-shrink:0;">
                <svg viewBox="0 0 24 24" width="18" height="18" fill="#ffffff" aria-hidden="true"><path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"/></svg>
            </span>
        </div>
    </div>

    <!-- Launcher bubble -->
    <button type="button" id="wp-bubble" aria-label="Open widget preview" aria-expanded="false"
            style="position:fixed; z-index:9999; bottom:20px; width:52px; height:52px; border-radius:50%;
                   border:none; cursor:pointer; box-shadow:0 4px 16px rgba(0,0,0,0.3);
                   display:flex; align-items:center; justify-content:center;
                   <?= $styling['position'] ?? 'bottom-right' ?>:20px;">
        <svg id="wp-bubble-icon" viewBox="0 0 24 24" width="28" height="28" fill="#ffffff" aria-hidden="true">
            <path d="M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm0 14H6l-2 2V4h16v12z"/>
        </svg>
        <span id="wp-bubble-close" aria-hidden="true" style="display:none; font-size:22px; line-height:1;">&times;</span>
    </button>
</div>
<script>
(function () {
    var panel  = document.getElementById('wp-panel');
    var bubble = document.getElementById('wp-bubble');
    var closeX = document.getElementById('wp-close');
    var isOpen = false;

    function read(fieldId, fallback) {
        var el = document.getElementById(fieldId);
        return el ? (el.value || fallback || '') : (fallback || '');
    }

    function currentStyle() {
        return {
            primary:    read('primary_color', '#0d6efd'),
            gradientTo: read('header_gradient_to', ''),
            headerText: read('header_text_color', '#ffffff'),
            accent:     read('accent_color', ''),
            botName:    read('bot_name', 'Assistant'),
            headerIcon: read('header_icon', ''),
            headerSub:  read('header_subtitle', ''),
            plIcon:     read('placeholder_icon', ''),
            plTitle:    read('placeholder_title', ''),
            plText:     read('placeholder_text', ''),
            position:   read('position', 'bottom-right'),
            theme:      (document.querySelector('input[name="panel_theme"]:checked') || {}).value || 'light'
        };
    }

    function renderPreview() {
        var s = currentStyle();
        var headerBg = s.gradientTo && s.gradientTo !== s.primary
            ? 'linear-gradient(135deg, ' + s.primary + ', ' + s.gradientTo + ')'
            : s.primary;
        var accent = s.accent || s.primary;
        var isDark = s.theme === 'dark';
        var surface = isDark ? '#111d35' : '#ffffff';
        var bodyBg  = isDark ? '#0a1628' : '#f5f7fa';
        var textSec = isDark ? '#bfc9d8' : '#4a5568';
        var borderColor = isDark ? 'rgba(255,255,255,0.08)' : 'rgba(0,0,0,0.08)';
        var titleColor  = isDark ? '#d0d8e0' : '#2c3e50';

        var side = s.position === 'bottom-left' ? 'left' : 'right';
        panel.style[side] = '20px';
        panel.style[side === 'left' ? 'right' : 'left'] = 'auto';
        bubble.style[side] = '20px';
        bubble.style[side === 'left' ? 'right' : 'left'] = 'auto';

        var header = document.getElementById('wp-header');
        header.style.background = headerBg;
        header.style.color = s.headerText;
        header.style.borderBottomColor = accent;
        document.getElementById('wp-send').style.background = accent;
        // The bubble follows the panel theme: light theme → light bubble with a
        // primary-colored icon; dark theme → primary/gradient bubble with a
        // white icon. The × inherits the bubble's text color.
        bubble.style.background = isDark ? headerBg : surface;
        bubble.style.color = isDark ? '#ffffff' : (s.primary || '#0d6efd');
        document.getElementById('wp-bubble-icon').setAttribute('fill', isDark ? '#ffffff' : (s.primary || '#0d6efd'));
        // Footer + input follow the panel theme (matches the real widget's
        // PANEL.footerBg / inputBg / inputColor).
        var footer = document.getElementById('wp-footer');
        footer.style.background = surface;
        footer.style.borderTopColor = borderColor;
        var input = document.getElementById('wp-input');
        input.style.background = isDark ? 'rgba(255,255,255,0.04)' : '#f0f2f5';
        input.style.color = isDark ? '#e8edf5' : '#1a1a2e';
        input.style.borderColor = isDark ? 'rgba(255,255,255,0.12)' : 'rgba(0,0,0,0.12)';

        var icon = document.getElementById('wp-header-icon');
        icon.textContent = s.headerIcon;
        icon.style.display = s.headerIcon ? 'flex' : 'none';

        var name = document.getElementById('wp-bot-name');
        name.textContent = s.botName;

        var sub = document.getElementById('wp-header-subtitle');
        sub.textContent = s.headerSub;
        sub.style.display = s.headerSub ? 'block' : 'none';

        var plIcon = document.getElementById('wp-placeholder-icon');
        plIcon.textContent = s.plIcon;
        plIcon.style.display = s.plIcon ? 'block' : 'none';

        var plTitle = document.getElementById('wp-placeholder-title');
        plTitle.textContent = s.plTitle;
        plTitle.style.display = s.plTitle ? 'block' : 'none';
        plTitle.style.color = titleColor;

        var plText = document.getElementById('wp-placeholder-text');
        plText.textContent = s.plText || 'Ask me anything!';
        plText.style.color = textSec;

        document.getElementById('wp-body').style.background = bodyBg;
        panel.style.background = surface;
        panel.style.borderColor = borderColor;
    }

    function setOpen(open) {
        isOpen = open;
        panel.style.display = open ? 'block' : 'none';
        panel.hidden = !open;
        bubble.setAttribute('aria-expanded', open ? 'true' : 'false');
        bubble.setAttribute('aria-label', open ? 'Close widget preview' : 'Open widget preview');
        document.getElementById('wp-bubble-icon').style.display = open ? 'none' : 'block';
        document.getElementById('wp-bubble-close').style.display = open ? 'block' : 'none';
    }

    bubble.addEventListener('click', function () { setOpen(!isOpen); renderPreview(); });
    closeX.addEventListener('click', function () { setOpen(false); });

    ['primary_color', 'header_gradient_to', 'header_text_color', 'accent_color',
     'bot_name', 'header_icon', 'header_subtitle', 'placeholder_icon',
     'placeholder_title', 'placeholder_text', 'position'].forEach(function (id) {
        var el = document.getElementById(id);
        if (el) el.addEventListener(el.tagName === 'SELECT' ? 'change' : 'input', renderPreview);
    });
    document.querySelectorAll('input[name="panel_theme"]').forEach(function (r) {
        r.addEventListener('change', renderPreview);
    });

    renderPreview();
})();
</script>
<?php
$previewHtml = ob_get_clean();
// Inject the preview markup just before the layout renders </body>: the layout
// prints $pageScripts before </body>, so piggyback on that variable.
$pageScripts = ($pageScripts ?? '') . $previewHtml;

// Render mode: form.php doubles as the Settings tab of the chatbot detail
// page. When included by show.php, $renderAsPartial is true and only the form
// body is echoed — the floating preview travels separately in $pageScripts
// (show.php passes it to the layout), so it is not emitted twice.
if (!empty($renderAsPartial)) {
    echo $pageContent;
} else {
    require __DIR__ . '/../dashboard/layout.php';
}
