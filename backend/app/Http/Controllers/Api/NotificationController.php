<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\NotificationService;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    protected $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    /**
     * Get notification settings
     */
    public function getSettings()
    {
        $settings = $this->notificationService->getSettings();
        return response()->json($settings);
    }

    /**
     * Update notification setting
     */
    public function updateSetting(Request $request, $key)
    {
        $validated = $request->validate([
            'email_enabled' => 'nullable|boolean',
            'sms_enabled' => 'nullable|boolean',
            'in_app_enabled' => 'nullable|boolean',
            'recipients' => 'nullable|array',
            'threshold_value' => 'nullable|integer',
        ]);

        $setting = $this->notificationService->updateSetting($key, $validated);

        return response()->json([
            'message' => 'Notification setting updated successfully',
            'setting' => $setting,
        ]);
    }

    /**
     * Get notification logs
     */
    public function getLogs(Request $request)
    {
        $filters = $request->only(['type', 'category', 'status', 'per_page']);
        $logs = $this->notificationService->getLogs($filters);

        return response()->json($logs);
    }

    /**
     * Test notification
     */
    public function testNotification(Request $request)
    {
        $validated = $request->validate([
            'type' => 'required|in:email,sms',
            'recipient' => 'required|string',
            'message' => 'required|string',
        ]);

        if ($validated['type'] === 'email') {
            $this->notificationService->sendEmail(
                $validated['recipient'],
                'Test Notification',
                $validated['message'],
                'test',
                auth()->id()
            );
        } else {
            $this->notificationService->sendSMS(
                $validated['recipient'],
                $validated['message'],
                'test',
                auth()->id()
            );
        }

        return response()->json([
            'message' => 'Test notification sent successfully',
        ]);
    }
}
