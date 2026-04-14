<?php
/**
 * PHPUnit Test for Text Utils
 * 
 * @package BeirutTime_OSINT_Pro
 * @group utils
 */

use Beiruttime\OSINT\Utils\TextUtils;

class TextUtilTest extends WP_UnitTestCase {
    
    /**
     * Test text cleaning
     */
    public function test_clean_text() {
        // Test with normal text
        $this->assertEquals('Hello World', TextUtils::cleanText('Hello World'));
        
        // Test with extra spaces
        $this->assertEquals('Hello World', TextUtils::cleanText('  Hello   World  '));
        
        // Test with empty string
        $this->assertEquals('', TextUtils::cleanText(''));
        
        // Test with null
        $this->assertEquals('', TextUtils::cleanText(null));
        
        // Test with control characters
        $this->assertEquals('Hello World', TextUtils::cleanText("Hello\x00World"));
    }
    
    /**
     * Test title normalization for deduplication
     */
    public function test_normalize_title_for_dedupe() {
        $title1 = 'عنوان الخبر الأول';
        $title2 = 'عنوان الخبر الأول';
        $title3 = 'عنوان مختلف';
        
        $norm1 = TextUtils::normalizeTitleForDedupe($title1);
        $norm2 = TextUtils::normalizeTitleForDedupe($title2);
        $norm3 = TextUtils::normalizeTitleForDedupe($title3);
        
        $this->assertEquals($norm1, $norm2, 'Same titles should normalize to same value');
        $this->assertNotEquals($norm1, $norm3, 'Different titles should normalize to different values');
    }
    
    /**
     * Test Arabic numerals conversion
     */
    public function test_arabic_numerals_conversion() {
        $title = 'الخبر رقم ١٢٣';
        $normalized = TextUtils::normalizeTitleForDedupe($title);
        
        $this->assertStringContainsString('123', $normalized);
        $this->assertStringNotContainsString('١٢٣', $normalized);
    }
    
    /**
     * Test title fingerprint generation
     */
    public function test_build_title_fingerprint() {
        $text1 = 'عنوان الخبر المهم';
        $text2 = 'عنوان الخبر المهم';
        $text3 = 'عنوان مختلف';
        
        $fp1 = TextUtils::buildTitleFingerprint($text1);
        $fp2 = TextUtils::buildTitleFingerprint($text2);
        $fp3 = TextUtils::buildTitleFingerprint($text3);
        
        $this->assertEquals($fp1, $fp2, 'Same text should produce same fingerprint');
        $this->assertNotEquals($fp1, $fp3, 'Different text should produce different fingerprint');
        $this->assertIsString($fp1);
        $this->assertEquals(32, strlen($fp1), 'MD5 hash should be 32 characters');
    }
    
    /**
     * Test keyword extraction
     */
    public function test_extract_keywords() {
        $text = 'هذا خبر مهم عن التطورات الأخيرة في المنطقة';
        $keywords = TextUtils::extractKeywords($text, 5);
        
        $this->assertIsArray($keywords);
        $this->assertLessThanOrEqual(5, count($keywords));
        $this->assertContains('خبر', $keywords);
        $this->assertContains('مهم', $keywords);
        
        // Test that stopwords are removed
        $this->assertNotContains('في', $keywords);
        $this->assertNotContains('من', $keywords);
        $this->assertNotContains('عن', $keywords);
    }
    
    /**
     * Test excerpt generation
     */
    public function test_excerpt() {
        $short_text = 'نص قصير';
        $long_text = str_repeat('كلمة ', 50);
        
        // Short text should remain unchanged
        $this->assertEquals($short_text, TextUtils::excerpt($short_text, 100));
        
        // Long text should be truncated
        $excerpt = TextUtils::excerpt($long_text, 50);
        $this->assertLessThanOrEqual(53, strlen($excerpt)); // 50 + '...'
        $this->assertStringEndsWith('...', $excerpt);
    }
    
    /**
     * Test keyword extraction limit
     */
    public function test_keyword_limit() {
        $text = str_repeat('كلمة مختلفة ', 20);
        $keywords = TextUtils::extractKeywords($text, 5);
        
        $this->assertCount(5, $keywords);
    }
}
