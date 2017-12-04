<?php

namespace App\Tests;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class URLControllerTest extends WebTestCase
{
    static $container;
    
    static $redis;
    
    public static function setUpBeforeClass()
    {
        //start the symfony kernel
        $kernel = static::createKernel();
        $kernel->boot();
        
        //get the DI container
        self::$container = $kernel->getContainer();
        
        //now we can instantiate our service (if you want a fresh one for
        //each test method, do this in setUp() instead
        self::$redis = self::$container->get('snc_redis.default');
    }
        
    public function testHomepage()
    {
        $client = self::createClient();
        $crawler = $client->request('GET', '/');

        $this->assertSame(200, $client->getResponse()->getStatusCode());
        $this->assertSame(1, $crawler->filter('html:contains("URL Shortener")')->count());
    }
    
    public function testShortenUrl()
    {
        $originalUrl = "https://www.reddit.com";
        $enc = '8eIhgDEtWdYA4kOaWqGm1AUEQQWfXB12zx7mNHzeSEcf';
        
        $client = self::createClient();
        $crawler = $client->request('GET', '/');
        
        $form = $crawler->filter('form')->form();
        
        $form->setValues([
            "long_url[url]" => $originalUrl
        ]);
        
        $crawler = $client->submit($form);
        
        $response = $client->getResponse();

        $this->assertContains($enc, $response->getContent());
        
        // hash the url
        $hash = hash('sha256', $originalUrl);
        
        $hashCreated = self::$redis->hget('URL:HASH:'.$hash, 'created');
        $this->assertNotEmpty($hashCreated);
        
        $hashUrl = self::$redis->hget('URL:HASH:'.$hash, 'url');
        $this->assertEquals($originalUrl, $hashUrl);
        
        $hashCount = self::$redis->hget('URL:HASH:'.$hash, 'hit');
        $this->assertEquals(0, $hashCount);
        
        $hashEnc = self::$redis->get('URL:ENC:'.$enc);
        $this->assertEquals($hashEnc, $hash);
        
        // perform a click
        $link = $crawler->filter('a.card-link')->link();
        
        $crawler = $client->click($link);
    }
   
    /**
     * @depends testShortenUrl
     */
    public function testTransfer()
    {
        $originalUrl = "https://www.reddit.com";
        $enc = '8eIhgDEtWdYA4kOaWqGm1AUEQQWfXB12zx7mNHzeSEcf';
        
        // hash the url
        $hash = hash('sha256', $originalUrl);
        
        $hit = self::$redis->hget('URL:HASH:'.$hash, 'hit');
        $this->assertEquals(1, $hit);
    }
    
    /**
     * @depends testShortenUrl
     */
    public function testRemoveUrl()
    {
        $originalUrl = "https://www.reddit.com";
        $enc = '8eIhgDEtWdYA4kOaWqGm1AUEQQWfXB12zx7mNHzeSEcf';
        
        $client = self::createClient();
        $crawler = $client->request('GET', '/');
        
        $link = $crawler->selectLink('Remove')->link();
        
        $crawler = $client->click($link);
        
        $response = $client->getResponse();
        $this->assertNotContains($enc, $response->getContent());
        
        // hash the url
        $hash = hash('sha256', $originalUrl);
        
        $hashCreated = self::$redis->hget('URL:HASH:'.$hash, 'created');
        $this->assertEmpty($hashCreated);
        
        $hashUrl = self::$redis->hget('URL:HASH:'.$hash, 'url');
        $this->assertEmpty($hashUrl);
        
        $hashCount = self::$redis->hget('URL:HASH:'.$hash, 'hit');
        $this->assertEmpty($hashCount);
        
        $hashEnc = self::$redis->get('URL:ENC:'.$enc);
        $this->assertEmpty($hashEnc);
    }
}


