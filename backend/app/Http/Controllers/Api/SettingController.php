<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class SettingController extends Controller
{
    public function index()
    {
        $settings = Setting::all()->groupBy('group')->map(function ($group) {
            return $group->mapWithKeys(function ($setting) {
                return [$setting->key => match ($setting->type) {
                    'integer' => (int) $setting->value,
                    'float' => (float) $setting->value,
                    'boolean' => filter_var($setting->value, FILTER_VALIDATE_BOOLEAN),
                    'json' => json_decode($setting->value, true),
                    default => $setting->value,
                }];
            });
        });

        return response()->json($settings);
    }

    public function update(Request $request)
    {
        $request->validate([
            'settings' => 'required|array',
            'settings.*.key' => 'required|string|exists:settings,key',
            'settings.*.value' => 'nullable',
        ]);

        foreach ($request->settings as $item) {
            Setting::set($item['key'], $item['value']);
        }

        AuditLog::logAction('settings.update', 'Setting', null, null, $request->settings, 'Settings updated');

        Cache::forget('settings.all');

        return response()->json([
            'message' => 'Settings updated successfully',
            'settings' => Setting::all()->groupBy('group'),
        ]);
    }
}