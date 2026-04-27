<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WebhookController extends Controller
{
    public function getWebhooks()
    {
        $webhooks = DB::table('webhooks')->get();
        return response()->json($webhooks);
    }

    public function createWebhook(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string',
            'url' => 'required|url',
            'events' => 'required|array',
            'secret' => 'nullable|string',
        ]);

        $id = DB::table('webhooks')->insertGetId([
            'name' => $validated['name'],
            'url' => $validated['url'],
            'events' => json_encode($validated['events']),
            'secret' => $validated['secret'] ?? bin2hex(random_bytes(16)),
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json(['message' => 'Webhook created', 'id' => $id], 201);
    }

    public function deleteWebhook($id)
    {
        DB::table('webhooks')->where('id', $id)->delete();
        return response()->json(['message' => 'Webhook deleted']);
    }
}
