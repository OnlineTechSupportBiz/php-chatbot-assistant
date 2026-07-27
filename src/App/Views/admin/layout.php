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
 * Super-admin layout (separate from admin account dashboard layout).
 *
 * Required variables:
 *   $pageTitle   — <title> content
 *   $pageContent — HTML body content
 *   $user        — authenticated user array
 */
\App\Auth\Session::start();
$currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$userName  = htmlspecialchars($user['name'] ?? '');
$csrfToken = \App\Auth\Session::csrfToken();

$isActive = fn(string $prefix): string => str_starts_with($currentPath, $prefix) ? ' active' : '';
$isExact  = fn(string $path): string => $currentPath === $path ? ' active' : '';
?><!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle ?? 'Admin - ' . ($user['brand_name'] ?? 'Chatbot Assistant')) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="/assets/css/theme.css" rel="stylesheet">
    <style>
        /* ── Skip to main content ──────────────────────────────────── */
        .skip-link {
            position: absolute;
            top: -100%;
            left: 0;
            z-index: 9999;
            padding: 0.5rem 1rem;
            background: var(--accent2, #0d9488);
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

        /* ── Focus visible ─────────────────────────────────────────── */
        :focus-visible {
            outline: 2px solid var(--accent2, #0d9488);
            outline-offset: 2px;
            border-radius: 2px;
        }
        .dark-mode :focus-visible {
            outline-color: #5eead4;
        }

        .sidebar-link.admin-link {
        }
        .sidebar-link.admin-link.active {
            background: rgba(13, 148, 136, 0.08);
        }
        .sidebar-link.admin-link .sidebar-icon {
            color: var(--accent2, #0d9488);
        }
        .switch-cell .form-check-input {
            width: 2.5em;
            height: 1.25em;
            cursor: pointer;
        }
        .switch-cell .form-check-input:checked {
            background-color: var(--accent2, #0d9488);
            border-color: var(--accent2, #0d9488);
        }
        .switch-cell .form-check-input:focus-visible {
            outline: 2px solid var(--accent2, #0d9488);
            outline-offset: 2px;
        }
        .perm-card {
            transition: box-shadow 0.15s ease;
        }
        .perm-card:hover,
        .perm-card:focus-within {
            box-shadow: 0 0 0 2px var(--accent2, #0d9488);
        }
    </style>
    <?= $pageStyles ?? '' ?>
</head>
<body class="admin-page">
    <a href="#main-content" class="skip-link">Skip to main content</a>
    <div class="dashboard-wrapper">
        <!-- Mobile header -->
        <div class="mobile-header d-lg-none" id="mobileHeader">
            <button class="sidebar-toggle" id="sidebarToggle" type="button" aria-label="Toggle sidebar">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
            </button>
            <a class="sidebar-brand" href="/admin"><?= htmlspecialchars($user['brand_name'] ?? 'Chatbot Assistant') ?></a>
        </div>

        <!-- Sidebar -->
        <aside class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <a class="sidebar-brand" href="/admin">
                    <?= htmlspecialchars($user['brand_name'] ?? 'Chatbot Assistant') ?>
                </a>
            </div>

            <nav class="sidebar-nav">
                <a href="/admin" class="sidebar-link admin-link<?= $isExact('/admin') ?>">
                    <svg class="sidebar-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg>
                    <span>Dashboard</span>
                </a>
                <a href="/admin/settings" class="sidebar-link admin-link<?= $isActive('/admin/settings') ?>">
                    <svg class="sidebar-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
                    <span>Settings</span>
                </a>
                <a href="/admin/settings/audit-log" class="sidebar-link sidebar-sub<?= $currentPath === '/admin/settings/audit-log' ? ' active' : '' ?>">
                    <svg class="sidebar-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
                    <span>Audit Logs</span>
                </a>
                <a href="/admin/settings/php-info" class="sidebar-link sidebar-sub<?= $currentPath === '/admin/settings/php-info' ? ' active' : '' ?>">
                    <svg class="sidebar-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
                    <span>PHP Info</span>
                </a>
            </nav>

            <div class="sidebar-footer">
                <span class="sidebar-user"><?= $userName ?></span>
                <button class="sidebar-logout" id="themeToggle" type="button" aria-label="Toggle dark mode" onclick="toggleTheme(event)">
                    <svg id="themeIconSun" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:none" aria-hidden="true"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/></svg>
                    <svg id="themeIconMoon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
                    <span id="themeLabel">Dark Mode</span>
                </button>
                <form method="POST" action="/logout" class="m-0">
                    <input type="hidden" name="_csrf" value="<?= $csrfToken ?>">
                    <button class="sidebar-logout" type="submit">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                        Logout
                    </button>
                </form>
                <div class="sidebar-branding">
                    <a href="https://onlinetechsupport.biz" target="_blank" rel="noopener noreferrer">
                        Powered by Online Tech Support LLC
                    </a>
                </div>
            </div>
        </aside>

        <!-- Overlay for mobile sidebar -->
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

        // ── Theme toggle ──
        applyTheme(getStoredTheme());
    });

    function getStoredTheme() {
        try { return localStorage.getItem('dashboard-theme') || 'light'; }
        catch(e) { return 'light'; }
    }

    function toggleTheme(e) {
        var next = getStoredTheme() === 'dark' ? 'light' : 'dark';
        storeTheme(next);
        applyTheme(next);
    }

    function storeTheme(theme) {
        try { localStorage.setItem('dashboard-theme', theme); } catch(e) {}
    }

    function applyTheme(theme) {
        var isDark = theme === 'dark';
        document.body.classList.toggle('dark-mode', isDark);
        document.documentElement.setAttribute('data-bs-theme', theme);
        var sun  = document.getElementById('themeIconSun');
        var moon = document.getElementById('themeIconMoon');
        var label = document.getElementById('themeLabel');
        if (sun)  sun.style.display   = isDark ? '' : 'none';
        if (moon) moon.style.display  = isDark ? 'none' : '';
        if (label) label.textContent  = isDark ? 'Light Mode' : 'Dark Mode';
    }
    </script>
    <?= $pageScripts ?? '' ?>
</body>
</html>
