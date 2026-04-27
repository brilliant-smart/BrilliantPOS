<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $fillable = ['key', 'value', 'type', 'group', 'label'];

    protected static function boot()
    {
        parent::boot();

        static::saved(function () {
            cache()->forget('settings.all');
        });

        static::deleted(function () {
            cache()->forget('settings.all');
        });
    }

    public static function get(string $key, $default = null)
    {
        $settings = cache()->rememberForever('settings.all', function () {
            return static::all()->keyBy('key');
        });

        $setting = $settings->get($key);

        if (!$setting) {
            return $default;
        }

        return match ($setting->type) {
            'integer' => (int) $setting->value,
            'float' => (float) $setting->value,
            'boolean' => filter_var($setting->value, FILTER_VALIDATE_BOOLEAN),
            'json' => json_decode($setting->value, true),
            default => $setting->value,
        };
    }

    public static function set(string $key, $value, string $type = 'string', string $group = 'general', ?string $label = null): self
    {
        $settingValue = match ($type) {
            'json' => is_string($value) ? $value : json_encode($value),
            'boolean' => $value ? '1' : '0',
            default => (string) $value,
        };

        return static::updateOrCreate(
            ['key' => $key],
            ['value' => $settingValue, 'type' => $type, 'group' => $group, 'label' => $label]
        );
    }
}