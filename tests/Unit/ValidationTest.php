<?php
/**
 * PHPUnit Test for Validation Utils
 * 
 * @package BeirutTime_OSINT_Pro
 * @group utils
 */

use Beiruttime\OSINT\Utils\Validation;

class ValidationTest extends WP_UnitTestCase {
    
    /**
     * Test JSON array parsing
     */
    public function test_parse_json_array() {
        // Test with valid JSON string
        $json = '{"key": "value", "number": 123}';
        $result = Validation::parseJsonArray($json);
        $this->assertIsArray($result);
        $this->assertEquals('value', $result['key']);
        $this->assertEquals(123, $result['number']);
        
        // Test with array input (should return as-is)
        $array = ['test' => 'data'];
        $this->assertEquals($array, Validation::parseJsonArray($array));
        
        // Test with invalid JSON
        $this->assertEmpty(Validation::parseJsonArray('not json'));
        
        // Test with empty string
        $this->assertEmpty(Validation::parseJsonArray(''));
        
        // Test with null
        $this->assertEmpty(Validation::parseJsonArray(null));
    }
    
    /**
     * Test isNotEmpty validation
     */
    public function test_is_not_empty() {
        $this->assertTrue(Validation::isNotEmpty('hello'));
        $this->assertTrue(Validation::isNotEmpty('  text  '));
        $this->assertFalse(Validation::isNotEmpty(''));
        $this->assertFalse(Validation::isNotEmpty('   '));
        $this->assertFalse(Validation::isNotEmpty(null));
        $this->assertFalse(Validation::isNotEmpty(123));
    }
    
    /**
     * Test range validation
     */
    public function test_is_in_range() {
        $this->assertTrue(Validation::isInRange(5, 1, 10));
        $this->assertTrue(Validation::isInRange(1, 1, 10));
        $this->assertTrue(Validation::isInRange(10, 1, 10));
        $this->assertTrue(Validation::isInRange(5.5, 1.0, 10.0));
        $this->assertFalse(Validation::isInRange(0, 1, 10));
        $this->assertFalse(Validation::isInRange(11, 1, 10));
        $this->assertFalse(Validation::isInRange('5', 1, 10));
    }
    
    /**
     * Test string sanitization
     */
    public function test_sanitize_string() {
        $input = '<script>alert("xss")</script>Hello World';
        $output = Validation::sanitizeString($input);
        
        $this->assertStringNotContainsString('<script>', $output);
        $this->assertStringContainsString('Hello World', $output);
        $this->assertEquals('Hello World', $output);
        
        // Test with non-string input
        $this->assertEquals('', Validation::sanitizeString(null));
        $this->assertEquals('', Validation::sanitizeString(123));
    }
    
    /**
     * Test email validation
     */
    public function test_is_valid_email() {
        $this->assertTrue(Validation::isValidEmail('test@example.com'));
        $this->assertTrue(Validation::isValidEmail('user.name@domain.org'));
        $this->assertFalse(Validation::isValidEmail('invalid'));
        $this->assertFalse(Validation::isValidEmail('test@'));
        $this->assertFalse(Validation::isValidEmail('@example.com'));
        $this->assertFalse(Validation::isValidEmail(''));
    }
    
    /**
     * Test URL validation
     */
    public function test_is_valid_url() {
        $this->assertTrue(Validation::isValidUrl('https://example.com'));
        $this->assertTrue(Validation::isValidUrl('http://example.com/path'));
        $this->assertFalse(Validation::isValidUrl('not-a-url'));
        $this->assertFalse(Validation::isValidUrl(''));
    }
    
    /**
     * Test array has keys validation
     */
    public function test_has_keys() {
        $array = ['name' => 'John', 'age' => 30, 'city' => 'NYC'];
        
        $this->assertTrue(Validation::hasKeys($array, ['name', 'age']));
        $this->assertTrue(Validation::hasKeys($array, ['name']));
        $this->assertFalse(Validation::hasKeys($array, ['name', 'country']));
        $this->assertFalse(Validation::hasKeys([], ['name']));
        $this->assertFalse(Validation::hasKeys('not array', ['name']));
        $this->assertFalse(Validation::hasKeys($array, 'not array'));
    }
}
