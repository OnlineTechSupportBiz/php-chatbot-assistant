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
 * Admin shell layout.
 *
 * Ported from the js-chatbot-assistant `(admin)/layout.tsx` + `sidebar.tsx` so
 * the PHP app renders identically: grid `.admin-shell`, navy two-layer
 * `.sidebar`/`.sidebar-inner` drawer, flat nav, no Bootstrap, native fonts.
 *
 * Required variables (set by the including view):
 *   $pageTitle   -- <title> content
 *   $pageContent -- HTML body content (captured via ob_start/ob_get_clean)
 * Optional:
 *   $pageStyles  -- inline <style> tags to inject in <head>
 *   $pageScripts -- inline <script> tags to inject before </body>
 *
 * Available in scope (passed from controller):
 *   $user -- authenticated user array
 */
\App\Auth\Session::start();
$currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$brandName = $user['brand_name'] ?? 'Chatbot Assistant';
$csrfToken = \App\Auth\Session::csrfToken();

// Flat nav, matching the JS port's sidebar exactly (single-admin model: no
// /admin screens, no sub-items). Active state is an exact match or a child path.
$navItems = [
    ['/dashboard', 'Dashboard'],
    ['/chatbots', 'Chatbots'],
    ['/leads', 'Leads'],
    ['/conversations', 'Conversations'],
    ['/settings', 'Settings'],
    ['/audit', 'Audit log'],
];
$isActive = fn(string $href): string =>
    ($currentPath === $href || str_starts_with($currentPath, $href . '/')) ? ' active' : '';
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle ?? 'Dashboard - ' . $brandName) ?></title>

    <!-- Theme (self-contained design system; native fonts, no external requests) -->
    <link href="/assets/css/theme.css" rel="stylesheet">
    <?= $pageStyles ?? '' ?>
</head>
<body>
    <a href="#main-content" class="skip-link">Skip to main content</a>

    <div class="admin-shell">
        <!-- Mobile-only topbar with the drawer toggle -->
        <header class="admin-topbar">
            <button type="button" class="nav-toggle" id="navToggle"
                    aria-label="Open navigation" aria-expanded="false" aria-controls="admin-sidebar">
                <span class="nav-toggle-bars" aria-hidden="true">
                    <span></span><span></span><span></span>
                </span>
            </button>
            <span class="admin-topbar-title"><?= htmlspecialchars($brandName) ?></span>
        </header>

        <!-- Scrim: rendered as a button so it is keyboard-reachable, like the JS port -->
        <button type="button" class="sidebar-overlay" id="sidebarOverlay"
                aria-label="Close navigation" hidden></button>

        <!-- Two-layer sidebar: .sidebar is the painted full-height box,
             .sidebar-inner is the scrolling column. CSS turns this into a
             static grid column at the lg breakpoint. -->
        <aside class="sidebar" id="admin-sidebar" aria-label="Main">
            <div class="sidebar-inner">
                <div class="sidebar-brand">
                    <?= htmlspecialchars($brandName) ?>
                    <button type="button" class="sidebar-close" id="sidebarClose"
                            aria-label="Close navigation">&times;</button>
                </div>
                <nav class="sidebar-nav">
                    <?php foreach ($navItems as [$href, $label]): ?>
                        <a href="<?= $href ?>" class="<?= trim($isActive($href)) ?>"<?= $isActive($href) ? ' aria-current="page"' : '' ?>><?= $label ?></a>
                    <?php endforeach; ?>
                </nav>
                <div class="sidebar-foot">
                    <form method="POST" action="/logout">
                        <input type="hidden" name="_csrf" value="<?= $csrfToken ?>">
                        <button type="submit" class="sidebar-signout">Sign out</button>
                    </form>
                </div>
            </div>
        </aside>

        <main class="admin-main" id="main-content">
            <?= $pageContent ?>
        </main>
    </div>

    <script>
    (function () {
        var toggle  = document.getElementById('navToggle');
        var close   = document.getElementById('sidebarClose');
        var sidebar = document.getElementById('admin-sidebar');
        var overlay = document.getElementById('sidebarOverlay');

        function isOpen() { return sidebar.classList.contains('is-open'); }
        function openSidebar() {
            sidebar.classList.add('is-open');
            overlay.hidden = false;
            document.body.style.overflow = 'hidden';
            toggle.setAttribute('aria-expanded', 'true');
            toggle.setAttribute('aria-label', 'Close navigation');
        }
        function closeSidebar() {
            sidebar.classList.remove('is-open');
            overlay.hidden = true;
            document.body.style.overflow = '';
            toggle.setAttribute('aria-expanded', 'false');
            toggle.setAttribute('aria-label', 'Open navigation');
        }
        if (toggle && sidebar && overlay) {
            toggle.addEventListener('click', function () {
                if (isOpen()) closeSidebar(); else openSidebar();
            });
            overlay.addEventListener('click', closeSidebar);
            if (close) close.addEventListener('click', closeSidebar);
            // Escape closes the drawer and returns focus to the toggle, so
            // keyboard users are not stranded (parity with the JS port).
            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape' && isOpen()) {
                    closeSidebar();
                    toggle.focus();
                }
            });
            // Navigating away must not leave the drawer open over the new page.
            sidebar.querySelectorAll('.sidebar-nav a').forEach(function (a) {
                a.addEventListener('click', closeSidebar);
            });
        }
    })();
    </script>
    <?= $pageScripts ?? '' ?>
</body>
</html>