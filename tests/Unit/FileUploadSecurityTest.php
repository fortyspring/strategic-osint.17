<?php
/**
 * Unit Tests for OSINT_File_Upload_Security
 * 
 * @package Beiruttime_OSINT_Pro
 * @subpackage Tests
 */

namespace Beiruttime\OSINT\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Test cases for file upload security functionality
 */
class FileUploadSecurityTest extends TestCase {
    
    /**
     * Test validate_uploaded_file with no file
     */
    public function test_validate_uploaded_file_with_no_file() {
        if (!class_exists('OSINT_File_Upload_Security')) {
            $this->markTestSkipped('OSINT_File_Upload_Security class not loaded');
        }
        
        $result = OSINT_File_Upload_Security::validate_uploaded_file(null, 'settings');
        
        $this->assertTrue(is_wp_error($result));
        $this->assertEquals('no_file', $result->get_error_code());
    }
    
    /**
     * Test validate_uploaded_file with empty array
     */
    public function test_validate_uploaded_file_with_empty_array() {
        if (!class_exists('OSINT_File_Upload_Security')) {
            $this->markTestSkipped('OSINT_File_Upload_Security class not loaded');
        }
        
        $result = OSINT_File_Upload_Security::validate_uploaded_file([], 'settings');
        
        $this->assertTrue(is_wp_error($result));
        $this->assertEquals('no_file', $result->get_error_code());
    }
    
    /**
     * Test validate_uploaded_file with upload error
     */
    public function test_validate_uploaded_file_with_upload_error() {
        if (!class_exists('OSINT_File_Upload_Security')) {
            $this->markTestSkipped('OSINT_File_Upload_Security class not loaded');
        }
        
        $file = [
            'error' => UPLOAD_ERR_NO_FILE,
            'name' => 'test.json',
            'tmp_name' => '',
            'size' => 0
        ];
        
        $result = OSINT_File_Upload_Security::validate_uploaded_file($file, 'settings');
        
        $this->assertTrue(is_wp_error($result));
        $this->assertEquals('upload_error', $result->get_error_code());
    }
    
    /**
     * Test validate_uploaded_file with file too large
     */
    public function test_validate_uploaded_file_with_file_too_large() {
        if (!class_exists('OSINT_File_Upload_Security')) {
            $this->markTestSkipped('OSINT_File_Upload_Security class not loaded');
        }
        
        $file = [
            'error' => UPLOAD_ERR_OK,
            'name' => 'test.json',
            'tmp_name' => '/tmp/test.json',
            'size' => 3000000 // 3MB, larger than 2MB limit for settings
        ];
        
        // Create a temporary file
        file_put_contents($file['tmp_name'], str_repeat('a', $file['size']));
        
        $result = OSINT_File_Upload_Security::validate_uploaded_file($file, 'settings');
        
        // Clean up
        unlink($file['tmp_name']);
        
        $this->assertTrue(is_wp_error($result));
        $this->assertEquals('file_too_large', $result->get_error_code());
    }
    
    /**
     * Test validate_uploaded_file with invalid MIME type
     */
    public function test_validate_uploaded_file_with_invalid_mime() {
        if (!class_exists('OSINT_File_Upload_Security')) {
            $this->markTestSkipped('OSINT_File_Upload_Security class not loaded');
        }
        
        $file = [
            'error' => UPLOAD_ERR_OK,
            'name' => 'test.exe',
            'tmp_name' => '/tmp/test.exe',
            'size' => 1024
        ];
        
        // Create a temporary file with executable content
        file_put_contents($file['tmp_name'], 'MZ' . str_repeat("\x00", 1022));
        
        $result = OSINT_File_Upload_Security::validate_uploaded_file($file, 'settings');
        
        // Clean up
        unlink($file['tmp_name']);
        
        $this->assertTrue(is_wp_error($result));
        $this->assertEquals('invalid_mime_type', $result->get_error_code());
    }
    
    /**
     * Test validate_uploaded_file with valid JSON settings file
     */
    public function test_validate_uploaded_file_with_valid_json() {
        if (!class_exists('OSINT_File_Upload_Security')) {
            $this->markTestSkipped('OSINT_File_Upload_Security class not loaded');
        }
        
        $file = [
            'error' => UPLOAD_ERR_OK,
            'name' => 'settings.json',
            'tmp_name' => '/tmp/settings.json',
            'size' => 512
        ];
        
        // Create a valid JSON settings file
        $json_content = json_encode([
            '_plugin' => 'beiruttime-osint-pro',
            '_exported_at' => time(),
            'so_enable_logging' => true,
            'so_alert_threshold' => 150
        ]);
        file_put_contents($file['tmp_name'], $json_content);
        
        $result = OSINT_File_Upload_Security::validate_uploaded_file($file, 'settings');
        
        // Clean up
        unlink($file['tmp_name']);
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('decoded', $result);
        $this->assertEquals('beiruttime-osint-pro', $result['decoded']['_plugin']);
    }
    
    /**
     * Test validate_uploaded_file with invalid JSON structure
     */
    public function test_validate_uploaded_file_with_invalid_json_structure() {
        if (!class_exists('OSINT_File_Upload_Security')) {
            $this->markTestSkipped('OSINT_File_Upload_Security class not loaded');
        }
        
        $file = [
            'error' => UPLOAD_ERR_OK,
            'name' => 'invalid.json',
            'tmp_name' => '/tmp/invalid.json',
            'size' => 256
        ];
        
        // Create an invalid JSON file (missing _plugin key)
        $json_content = json_encode([
            'some_key' => 'some_value'
        ]);
        file_put_contents($file['tmp_name'], $json_content);
        
        $result = OSINT_File_Upload_Security::validate_uploaded_file($file, 'settings');
        
        // Clean up
        unlink($file['tmp_name']);
        
        $this->assertTrue(is_wp_error($result));
        $this->assertEquals('invalid_json', $result->get_error_code());
    }
    
    /**
     * Test import_settings with valid data
     */
    public function test_import_settings_with_valid_data() {
        if (!class_exists('OSINT_File_Upload_Security')) {
            $this->markTestSkipped('OSINT_File_Upload_Security class not loaded');
        }
        
        $validated_file = [
            'decoded' => [
                '_plugin' => 'beiruttime-osint-pro',
                '_exported_at' => time(),
                'so_enable_logging' => true,
                'so_alert_threshold' => 200,
                'invalid_option' => 'should_be_skipped'
            ]
        ];
        
        $result = OSINT_File_Upload_Security::import_settings($validated_file);
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('restored', $result);
        $this->assertArrayHasKey('skipped', $result);
    }
    
    /**
     * Test sanitize_option_value with boolean options
     */
    public function test_sanitize_option_value_boolean() {
        if (!class_exists('OSINT_File_Upload_Security')) {
            $this->markTestSkipped('OSINT_File_Upload_Security class not loaded');
        }
        
        $reflection = new \ReflectionClass('OSINT_File_Upload_Security');
        $method = $reflection->getMethod('sanitize_option_value');
        $method->setAccessible(true);
        
        $result = $method->invoke(null, 'so_enable_logging', 'true');
        $this->assertTrue($result);
        
        $result = $method->invoke(null, 'so_enable_logging', 1);
        $this->assertTrue($result);
        
        $result = $method->invoke(null, 'so_enable_logging', false);
        $this->assertFalse($result);
    }
    
    /**
     * Test sanitize_option_value with integer options
     */
    public function test_sanitize_option_value_integer() {
        if (!class_exists('OSINT_File_Upload_Security')) {
            $this->markTestSkipped('OSINT_File_Upload_Security class not loaded');
        }
        
        $reflection = new \ReflectionClass('OSINT_File_Upload_Security');
        $method = $reflection->getMethod('sanitize_option_value');
        $method->setAccessible(true);
        
        $result = $method->invoke(null, 'so_alert_threshold', '150');
        $this->assertSame(150, $result);
        
        $result = $method->invoke(null, 'so_popup_threshold', 180);
        $this->assertSame(180, $result);
    }
    
    /**
     * Test sanitize_option_value with array options
     */
    public function test_sanitize_option_value_array() {
        if (!class_exists('OSINT_File_Upload_Security')) {
            $this->markTestSkipped('OSINT_File_Upload_Security class not loaded');
        }
        
        $reflection = new \ReflectionClass('OSINT_File_Upload_Security');
        $method = $reflection->getMethod('sanitize_option_value');
        $method->setAccessible(true);
        
        $input = ['source1', 'source2', '<script>alert("xss")</script>'];
        $result = $method->invoke(null, 'so_sources', $input);
        
        $this->assertIsArray($result);
        $this->assertCount(3, $result);
        $this->assertEquals('source1', $result[0]);
        $this->assertEquals('source2', $result[1]);
        // XSS attempt should be sanitized
        $this->assertNotContains('<script>', $result[2]);
    }
}
