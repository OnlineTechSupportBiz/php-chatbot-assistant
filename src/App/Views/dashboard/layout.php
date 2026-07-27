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
 * Dashboard sidebar layout.
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
$userName  = htmlspecialchars($user['name'] ?? '');
$csrfToken = \App\Auth\Session::csrfToken();

// Fetch brand name from the user's own record
$brandName = $user['brand_name'] ?? 'Chatbot Assistant';

// Determine active sidebar item
$isActive = fn(string $prefix): string => str_starts_with($currentPath, $prefix) ? ' active' : '';
?><!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle ?? 'Dashboard - ' . $brandName) ?></title>

    <!-- Geist font + Geist Mono -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/geist@1.3.0/dist/font/css/geist.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/geist@1.3.0/dist/font/css/geist-mono.min.css">

    <!-- Phosphor icons (regular weight) -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@phosphor-icons/web@2.1.1/src/regular/style.css">

    <!-- Bootstrap 5.3 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Theme -->
    <link href="/assets/css/theme.css" rel="stylesheet">

    <style>
        .skip-link {
            position: absolute;
            top: -100%;
            left: 0;
            z-index: 9999;
            padding: 0.5rem 1rem;
            background: var(--accent);
            color: #fff;
            text-decoration: none;
            font-weight: 600;
            border-radius: 0 0 0.25rem 0;
            transition: top 0.1s;
        }
        .skip-link:focus {
            top: 0;
            outline: 2px solid #fff;
            outline-offset: 2px;
        }
        :focus-visible {
            outline: 2px solid var(--accent);
            outline-offset: 2px;
            border-radius: 2px;
        }
    </style>
    <?= $pageStyles ?? '' ?>
</head>
<body>
    <a href="#main-content" class="skip-link">Skip to main content</a>
    <div class="dashboard-wrapper">
        <!-- Mobile header -->
        <div class="mobile-header d-lg-none" id="mobileHeader">
            <button class="sidebar-toggle" id="sidebarToggle" type="button" aria-label="Toggle sidebar">
                <i class="ph ph-list" style="font-size:1.25rem;"></i>
            </button>
            <a class="sidebar-brand" href="/dashboard"><?= htmlspecialchars($brandName) ?></a>
        </div>

        <!-- Sidebar -->
        <aside class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <a class="sidebar-brand" href="/dashboard"><?= htmlspecialchars($brandName) ?></a>
            </div>

            <nav class="sidebar-nav">
                <a href="/dashboard" class="sidebar-link<?= $isActive('/dashboard') ?>">
                    <i class="ph ph-chart-bar sidebar-icon"></i>
                    <span>Dashboard</span>
                </a>
                <a href="/chatbots" class="sidebar-link<?= $isActive('/chatbots') ?>">
                    <i class="ph ph-chat-circle sidebar-icon"></i>
                    <span>Chatbots</span>
                </a>
                <a href="/settings" class="sidebar-link<?= $isActive('/settings') ?>">
                    <i class="ph ph-gear sidebar-icon"></i>
                    <span>Settings</span>
                </a>
                <a href="/settings/audit-log" class="sidebar-link sidebar-sub<?= $currentPath === '/settings/audit-log' ? ' active' : '' ?>">
                    <i class="ph ph-notebook sidebar-icon"></i>
                    <span>Audit Log</span>
                </a>
                <a href="/settings/php-info" class="sidebar-link sidebar-sub<?= $currentPath === '/settings/php-info' ? ' active' : '' ?>">
                    <i class="ph ph-info sidebar-icon"></i>
                    <span>PHP Info</span>
                </a>
                <?php if (in_array($user['role'] ?? '', ['admin'], true)): ?>
                <div class="sidebar-separator"><span>Admin</span></div>
                <a href="/admin" class="sidebar-link admin-link<?= $isActive('/admin') ?>">
                    <i class="ph ph-shield sidebar-icon"></i>
                    <span>Admin Dashboard</span>
                </a>
                <?php endif; ?>
            </nav>

            <div class="sidebar-footer">
                <span class="sidebar-user"><?= $userName ?></span>
                <button class="sidebar-action" id="themeToggle" type="button" aria-label="Toggle dark mode">
                    <i id="themeIcon" class="ph ph-moon" style="font-size:1rem;"></i>
                    <span id="themeLabel">Dark Mode</span>
                </button>
                <form method="POST" action="/logout" class="m-0">
                    <input type="hidden" name="_csrf" value="<?= $csrfToken ?>">
                    <button class="sidebar-action" type="submit">
                        <i class="ph ph-sign-out" style="font-size:1rem;"></i>
                        Logout
                    </button>
                </form>
                <div class="sidebar-branding">
                    <a href="https://onlinetechsupport.biz" target="_blank" rel="noopener noreferrer">
                        Developed by Online Tech Support LLC
                    </a>
                </div>
            </div>
        </aside>

        <!-- Overlay -->
        <div class="sidebar-overlay" id="sidebarOverlay"></div>

        <!-- Main Content -->
        <main class="main-content" id="main-content">
            <?= $pageContent ?>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        var toggle  = document.getElementById('sidebarToggle');
        var sidebar = document.getElementById('sidebar');
        var overlay = document.getElementById('sidebarOverlay');

        if (toggle && sidebar && overlay) {
            function openSidebar() {
                sidebar.classList.add('open');
                overlay.classList.add('show');
                document.body.style.overflow = 'hidden';
            }
            function closeSidebar() {
                sidebar.classList.remove('open');
                overlay.classList.remove('show');
                document.body.style.overflow = '';
            }
            toggle.addEventListener('click', function(e) {
                e.stopPropagation();
                if (sidebar.classList.contains('open')) {
                    closeSidebar();
                } else {
                    openSidebar();
                }
            });
            overlay.addEventListener('click', closeSidebar);
        }

        // Theme toggle
        var themeToggle = document.getElementById('themeToggle');
        var icon        = document.getElementById('themeIcon');
        var label       = document.getElementById('themeLabel');

        function getTheme() {
            return localStorage.getItem('dashboard-theme') || 'light';
        }
        function setTheme(theme) {
            var isDark = theme === 'dark';
            document.body.classList.toggle('dark-mode', isDark);
            document.documentElement.setAttribute('data-bs-theme', theme);
            if (icon) {
                icon.className = isDark ? 'ph ph-sun' : 'ph ph-moon';
                icon.style.fontSize = '1rem';
            }
            if (label) label.textContent = isDark ? 'Light Mode' : 'Dark Mode';
            localStorage.setItem('dashboard-theme', theme);
        }
        setTheme(getTheme());

        if (themeToggle) {
            themeToggle.addEventListener('click', function() {
                var next = getTheme() === 'dark' ? 'light' : 'dark';
                setTheme(next);
            });
        }
    });
    </script>
    <?= $pageScripts ?? '' ?>
</body>
</html>
