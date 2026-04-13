<?php
/**
 * PHPUnit Test for Cache Handler
 * 
 * @package BeirutTime_OSINT_Pro
 * @group cache
 */

class CacheHandlerTest extends WP_UnitTestCase {
    
    private $cache_handler;
    
    public function setUp(): void {
        parent::setUp();
        require_once OSINT_PRO_PLUGIN_DIR . 'includes/cache/class-cache-handler.php';
        $this->cache_handler = OSINT_Cache_Handler::get_instance();
    }
    
    /**
     * Test cache set and get
     */
    public function test_set_and_get() {
        $key = 'test_key_' . time();
        $value = array('data' => 'test_value', 'number' => 123);
        
        $result = $this->cache_handler->set($key, $value, 60);
        $this->assertTrue($result);
        
        $retrieved = $this->cache_handler->get($key);
        $this->assertEquals($value, $retrieved);
    }
    
    /**
     * Test cache delete
     */
    public function test_delete() {
        $key = 'test_delete_' . time();
        $value = 'delete_test';
        
        $this->cache_handler->set($key, $value, 60);
        $this->assertEquals($value, $this->cache_handler->get($key));
        
        $result = $this->cache_handler->delete($key);
        $this->assertTrue($result);
        
        $this->assertFalse($this->cache_handler->get($key));
    }
    
    /**
     * Test cache expiration
     */
    public function test_expiration() {
        $key = 'test_expire_' . time();
        $value = 'expire_test';
        
        // Set with 1 second TTL
        $this->cache_handler->set($key, $value, 1);
        $this->assertEquals($value, $this->cache_handler->get($key));
        
        // Wait for expiration
        sleep(2);
        
        $this->assertFalse($this->cache_handler->get($key));
    }
    
    /**
     * Test connection test
     */
    public function test_connection() {
        $result = $this->cache_handler->test_connection();
        $this->assertTrue($result);
    }
    
    /**
     * Test get stats
     */
    public function test_get_stats() {
        $stats = $this->cache_handler->get_stats();
        
        $this->assertIsArray($stats);
        $this->assertArrayHasKey('backend', $stats);
        $this->assertContains($stats['backend'], array('wp', 'redis', 'memcached'));
    }
}
