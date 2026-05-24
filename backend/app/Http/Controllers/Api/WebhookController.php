<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Webhook;
use Illuminate\Http\Request;

class WebhookController extends Controller
{
    public function getWebhooks()
    {
        return response()->json(
            Webhook::all()->map(function ($webhook) {
                // Mask the secret — only show last 4 characters
                if ($webhook->secret) {
                    $webhook->secret = str_repeat('•', max(0, strlen($webhook->secret) - 4)) . substr($webhook->secret, -4);
                }
                return $webhook;
            })
        );
    }

    /**
     * Allowed webhook event types
     */
    private const ALLOWED_EVENTS = [
        'sale.created',
        'sale.voided',
        'purchase_order.created',
        'purchase_order.approved',
        'purchase_order.received',
        'inventory.low_stock',
        'inventory.out_of_stock',
        'batch.expiring',
        'batch.expired',
    ];

    /**
     * Blocked URL patterns for SSRF protection
     */
    private const BLOCKED_URL_PATTERNS = [
        '/^127\./',
        '/^10\./',
        '/^172\.(1[6-9]|2[0-9]|3[01])\./',
        '/^192\.168\./',
        '/^169\.254\./',
        '/^0\./',
        '/localhost/i',
        '/\[::1\]/',
        '/\[fc00:/i',
        '/\[fe80:/i',
    ];

    public function createWebhook(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'url' => 'required|url|max:2048',
            'events' => 'required|array|min:1',
            'events.*' => 'required|string|in:' . implode(',', self::ALLOWED_EVENTS),
            'secret' => 'nullable|string|max:255',
        ]);

        // SSRF protection: block internal/private network URLs
        $url = $validated['url'];
        $host = parse_url($url, PHP_URL_HOST) ?? '';

        // Check the host against blocked patterns
        foreach (self::BLOCKED_URL_PATTERNS as $pattern) {
            if (preg_match($pattern, $host)) {
                return response()->json([
                    'message' => 'Webhook URLs pointing to internal networks are not allowed.',
                ], 422);
            }
        }

        // Also resolve the hostname and check the resulting IP
        if ($host) {
            $records = @dns_get_record($host, DNS_A);
            if ($records) {
                foreach ($records as $record) {
                    $ip = $record['ip'] ?? '';
                    foreach (self::BLOCKED_URL_PATTERNS as $pattern) {
                        if (preg_match($pattern, $ip)) {
                            return response()->json([
                                'message' => 'Webhook URL resolves to an internal network address.',
                            ], 422);
                        }
                    }
                }
            }
        }

        $webhook = Webhook::create([
            'name' => $validated['name'],
            'url' => $validated['url'],
            'events' => $validated['events'],
            'secret' => $validated['secret'] ?? bin2hex(random_bytes(16)),
            'is_active' => true,
        ]);

        // Mask secret in response
        $webhook->secret = str_repeat('•', max(0, strlen($webhook->secret) - 4)) . substr($webhook->secret, -4);

        return response()->json(['message' => 'Webhook created', 'id' => $webhook->id, 'webhook' => $webhook], 201);
    }

    public function deleteWebhook($id)
    {
        Webhook::destroy($id);
        return response()->json(['message' => 'Webhook deleted']);
    }
}