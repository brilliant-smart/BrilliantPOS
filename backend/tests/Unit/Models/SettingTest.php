<?php

namespace Tests\Unit\Models;

use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class SettingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_get_returns_typed_integer(): void
    {
        Setting::set('test_int', '42', 'integer');

        $result = Setting::get('test_int');

        $this->assertIsInt($result);
        $this->assertEquals(42, $result);
    }

    public function test_get_returns_typed_float(): void
    {
        Setting::set('test_float', '3.14', 'float');

        $result = Setting::get('test_float');

        $this->assertIsFloat($result);
        $this->assertEqualsWithDelta(3.14, $result, 0.001);
    }

    public function test_get_returns_typed_boolean(): void
    {
        Setting::set('test_bool_true', true, 'boolean');
        Setting::set('test_bool_false', false, 'boolean');

        $this->assertTrue(Setting::get('test_bool_true'));
        $this->assertFalse(Setting::get('test_bool_false'));
    }

    public function test_get_returns_decoded_json(): void
    {
        Setting::set('test_json', ['a' => 1, 'b' => 2], 'json');

        $result = Setting::get('test_json');

        $this->assertIsArray($result);
        $this->assertEquals(['a' => 1, 'b' => 2], $result);
    }

    public function test_get_returns_default_when_key_missing(): void
    {
        $result = Setting::get('nonexistent_key', 'fallback_value');

        $this->assertEquals('fallback_value', $result);
    }

    public function test_set_creates_or_updates_setting(): void
    {
        Setting::set('test_new_key', 'initial_value');

        $this->assertDatabaseHas('settings', ['key' => 'test_new_key', 'value' => 'initial_value']);

        Setting::set('test_new_key', 'updated_value');

        $this->assertDatabaseHas('settings', ['key' => 'test_new_key', 'value' => 'updated_value']);
    }

    public function test_cache_is_invalidated_on_save(): void
    {
        Setting::set('cache_test', 'original');

        // First get caches it
        $this->assertEquals('original', Setting::get('cache_test'));

        // Update should invalidate cache
        Setting::set('cache_test', 'updated');

        // Should return the new value, not the cached old value
        $this->assertEquals('updated', Setting::get('cache_test'));
    }
}