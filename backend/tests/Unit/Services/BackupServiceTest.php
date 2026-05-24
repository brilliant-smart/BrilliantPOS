<?php

namespace Tests\Unit\Services;

use App\Http\Controllers\Api\BackupController;
use Tests\TestCase;

class BackupServiceTest extends TestCase
{
    private BackupController $controller;

    protected function setUp(): void
    {
        parent::setUp();
        $this->controller = new BackupController();
    }

    // ---- splitSqlStatements ----

    public function test_splits_simple_statements(): void
    {
        $sql = "DROP TABLE IF EXISTS `users`; CREATE TABLE `users` (id INT);";

        $result = $this->controller->splitSqlStatements($sql);

        $this->assertCount(2, $result);
        $this->assertStringStartsWith('DROP TABLE', $result[0]);
        $this->assertStringStartsWith('CREATE TABLE', $result[1]);
    }

    public function test_ignores_semicolons_inside_single_quoted_strings(): void
    {
        $sql = "INSERT INTO `cache` (`key`, `value`) VALUES ('my-key', 'i:1779338013;');";

        $result = $this->controller->splitSqlStatements($sql);

        $this->assertCount(1, $result);
        $this->assertStringContainsString('i:1779338013;', $result[0]);
    }

    public function test_ignores_semicolons_in_serialized_php_data(): void
    {
        $sql = "INSERT INTO `cache` (`key`, `value`) VALUES ('settings', 'O:8:\"stdClass\":2:{s:2:\"id\";i:1;s:4:\"name\";s:4:\"test\";}');";

        $result = $this->controller->splitSqlStatements($sql);

        $this->assertCount(1, $result);
        $this->assertStringContainsString('stdClass', $result[0]);
    }

    public function test_handles_multiple_statements_with_embedded_semicolons(): void
    {
        $sql = "SET FOREIGN_KEY_CHECKS=0;\n"
             . "DROP TABLE IF EXISTS `cache`;\n"
             . "INSERT INTO `cache` (`key`, `value`) VALUES ('k1', 'i:1;');\n"
             . "INSERT INTO `cache` (`key`, `value`) VALUES ('k2', 's:3:\"abc\";');\n"
             . "SET FOREIGN_KEY_CHECKS=1;";

        $result = $this->controller->splitSqlStatements($sql);

        $this->assertCount(5, $result);
        $this->assertStringStartsWith('SET FOREIGN_KEY_CHECKS=0', $result[0]);
        $this->assertStringContainsString("'i:1;'", $result[2]);
        $this->assertStringContainsString("'s:3", $result[3]);
    }

    public function test_ignores_sql_comments(): void
    {
        $sql = "-- This is a comment\n"
             . "DROP TABLE IF EXISTS `users`;\n"
             . "-- Another comment\n"
             . "CREATE TABLE `users` (id INT);\n";

        $result = $this->controller->splitSqlStatements($sql);

        $this->assertCount(2, $result);
        // Comments should not appear as separate statements
        foreach ($result as $stmt) {
            $this->assertDoesNotMatchRegularExpression('/^\s*--/', $stmt);
        }
    }

    public function test_handles_backtick_identifiers(): void
    {
        $sql = "INSERT INTO `my;table` (`col;name`) VALUES ('value');";

        $result = $this->controller->splitSqlStatements($sql);

        $this->assertCount(1, $result);
        $this->assertStringContainsString('my;table', $result[0]);
    }

    public function test_handles_escaped_single_quotes(): void
    {
        $sql = "INSERT INTO `users` (`name`) VALUES ('O\\'Brien');";

        $result = $this->controller->splitSqlStatements($sql);

        $this->assertCount(1, $result);
        $this->assertStringContainsString("O\\'Brien", $result[0]);
    }

    public function test_handles_doubled_single_quotes(): void
    {
        $sql = "INSERT INTO `users` (`name`) VALUES ('It''s a test');";

        $result = $this->controller->splitSqlStatements($sql);

        $this->assertCount(1, $result);
        $this->assertStringContainsString("It''s a test", $result[0]);
    }

    public function test_handles_double_quoted_strings(): void
    {
        $sql = 'INSERT INTO `data` (`val`) VALUES ("hello;world");';

        $result = $this->controller->splitSqlStatements($sql);

        $this->assertCount(1, $result);
        $this->assertStringContainsString('hello;world', $result[0]);
    }

    public function test_skips_empty_statements(): void
    {
        $sql = ";;; DROP TABLE `users`;;; CREATE TABLE `users` (id INT);;;";

        $result = $this->controller->splitSqlStatements($sql);

        $this->assertCount(2, $result);
    }

    public function test_handles_full_create_table_statement(): void
    {
        $sql = "DROP TABLE IF EXISTS `users`;\n"
             . "CREATE TABLE `users` (\n"
             . "  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,\n"
             . "  `name` varchar(255) NOT NULL,\n"
             . "  PRIMARY KEY (`id`)\n"
             . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;\n"
             . "INSERT INTO `users` (`id`, `name`) VALUES (1, 'Admin;User');";

        $result = $this->controller->splitSqlStatements($sql);

        $this->assertCount(3, $result);
        $this->assertStringStartsWith('DROP TABLE', $result[0]);
        $this->assertStringStartsWith('CREATE TABLE', $result[1]);
        $this->assertStringContainsString('Admin;User', $result[2]);
    }

    public function test_handles_statement_without_trailing_semicolon(): void
    {
        $sql = "SET FOREIGN_KEY_CHECKS=0";

        $result = $this->controller->splitSqlStatements($sql);

        $this->assertCount(1, $result);
        $this->assertEquals('SET FOREIGN_KEY_CHECKS=0', $result[0]);
    }

    public function test_real_world_cache_insert_with_semicolons(): void
    {
        $value = "O:8:\\\"stdClass\\\":13:{s:2:\\\"id\\\";i:1;s:20:\\\"ip_whitelist_enabled\\\";i:0;s:15:\\\"two_fa_required\\\";i:0;}";
        $sql = "INSERT INTO `cache` (`key`, `value`, `expiration`) VALUES ('laravel-cache-security_settings', '{$value}', '1779338253');";

        $result = $this->controller->splitSqlStatements($sql);

        $this->assertCount(1, $result);
        $this->assertStringContainsString('ip_whitelist_enabled', $result[0]);
    }

    // ---- EPHEMERAL_TABLES constant ----

    public function test_ephemeral_tables_constant_includes_cache(): void
    {
        $reflection = new \ReflectionClass(BackupController::class);
        $constants = $reflection->getConstants();

        $this->assertArrayHasKey('EPHEMERAL_TABLES', $constants);
        $this->assertContains('cache', $constants['EPHEMERAL_TABLES']);
        $this->assertContains('sessions', $constants['EPHEMERAL_TABLES']);
        $this->assertContains('jobs', $constants['EPHEMERAL_TABLES']);
    }
}