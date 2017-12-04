<?php

namespace App\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use App\Form\LongURLType;
use Symfony\Component\HttpFoundation\Request;
use Predis\Client;
use App\Encoder\Base62;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Form\FormError;
use Psr\Log\LoggerInterface;

class URLController extends AbstractController
{
    const PAGE_SIZE = 10;
    
    /**
     * 
     * @var Client
     */
    private $redis;
    
    /**
     * 
     * @var LoggerInterface
     */
    private $logger;
    
    /**
     * 
     * @return Client
     */
    protected function getRedis()
    {
        return $this->redis;
    }
    
    /**
     *
     * @return LoggerInterface
     */
    protected function getLogger()
    {
        return $logger;
    }
    
    /**
     * 
     * @param Client $redis
     */
    public function setRedis(Client $redis)
    {
        $this->redis = $redis;
    }

    /**
     * 
     * @param LoggerInterface $logger
     */
    public function setLogger(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }
    
    /**
     *
     * @param string $key
     * @return string
     */
    protected function getHashFromKey($key)
    {
        // lets fetch back the matching hash
        $hash = $this->getRedis()->get('URL:ENC:'.$key);
        
        if (empty($hash)) {
            throw $this->createNotFoundException('URL Key Not Found.');
        }
        
        return $hash;
    }
    
    /**
     *
     * @param string $hash
     * @return string
     */
    protected function getURLFromHash($hash)
    {
        // now take take the hash and get the original url
        $url = $this->getRedis()->hget('URL:HASH:'.$hash, 'url');
        
        if (empty($url)) {
            throw $this->createNotFoundException('URL Not Found.');
        }
        
        return $url;
    }
    
    /**
     * 
     * @param string $hash
     * @return NULL|string
     */
    protected function getEncodedHash($hash)
    {
        $encoded = null;
        
        // lets convert the hash characters to numbers and then convert the number
        $chars = str_split($hash, 3);
        
        // now for each element of chars, lets
        foreach ($chars as $item) {
            $num = base_convert($item, 16, 10);
            // now convert the number to base62
            $b62Num = Base62::convert($num, 10, 62);
            
            $encoded .= strval($b62Num);
        }

        return $encoded;
    }
    
    /**
     * @param Request $request
     * @Route("/", name="home", requirements={"page": "\d+"})
     * @Template()
     */
    public function index(Request $request, $page = 1)
    {
        $errors = [];
        $originalUrl = null;
        $url = null;
        
        $form = $this->createForm(LongURLType::class);
        
        if ($request->isMethod(Request::METHOD_POST)) {
            $form->handleRequest($request);
            if ($form->isValid()) {

                $originalUrl = $form->get('url')->getData();
           
                // hash the url
                $hash = hash('sha256', $originalUrl);
                
                // encoded
                $encoded = $this->getEncodedHash($hash);
                
                // if the hash already exists, it means we've already created a short url for it
                $redis = $this->getRedis();
                
                $now = new \DateTime();
                if ($redis->hsetnx('URL:HASH:'.$hash, 'created', $now->format(\DateTime::W3C))) {
                    // now that we have an encoded value, lets map the encoded value back to the hash key
                    $this->getRedis()->transaction()
                    ->hsetnx('URL:HASH:'.$hash, 'url', $originalUrl)
                    ->hset('URL:HASH:'.$hash, 'hit', 0)
                    ->set('URL:ENC:'.$encoded, $hash)
                    ->lpush('URL:ENC:HASH', [$encoded])
                    ->execute();
                }

                $url = $this->generateUrl('transfer', [ 'key' => $encoded ], UrlGenerator::ABSOLUTE_URL);

                // new form
                $form = $this->createForm(LongURLType::class);
            }
            else {               
                foreach ($form->getErrors(true) as $formError) {
                    if (! $formError instanceof FormError) {
                        continue;
                    }   
                    $errors[] = $formError->getMessage();
                }
            }
        }

        $newest = $this->getRedis()->lrange('URL:ENC:HASH', 0, 9);
                
        return [
            'errors' => $errors,
            'form' => $form->createView(),
            'originalUrl' => $originalUrl,
            'url' => $url,
            'list' => $newest,
        ];
    }
    
    /**
     * 
     * @param Request $request
     * @param string $key
     * @Route("/{key}", name="transfer", requirements={"key": "[a-zA-Z0-9]+"})
     */
    public function transfer(Request $request, $key)
    {
        // lets fetch back the matching hash
        $hash = $this->getHashFromKey($key);

        // now take take the hash and get the original url
        $url = $this->getURLFromHash($hash);

        $now = new \DateTime();
        
        $details =json_encode([
            'ip' => $request->getClientIp(),
            'agent' => $request->headers->get('User-Agent'),
            'clicked' => $now->format(\DateTime::W3C)
        ]);
        
        // details
        $this->getRedis()->transaction()
        ->hincrby('URL:HASH:'.$hash, 'hit', 1)
        ->lpush('URL:HASH:'.$hash.':hits', [ $details ])
        ->execute();

        return $this->redirect($url);
    }
    
    /**
     * 
     * @param Request $request
     * @param string $key
     * @Route("/d/{key}", name="details", requirements={"key": "[a-zA-Z0-9]+"})
     * @Template()
     */
    public function details(Request $request, $key, $page = 1) 
    {        
        $hash = $this->getHashFromKey($key);

        $details = $this->getRedis()->hgetall('URL:HASH:'.$hash);
                
        // list
        $newest = $this->getRedis()->lrange('URL:HASH:'.$hash.':hits', 0, 9);
        
        // decode json hit data
        foreach ($newest as &$hit) {
            $hit = json_decode($hit, true);
        }
        
        return [
            'encoded' => $key,
            'details' => $details,
            'list' => $newest,
        ];
    }
    
    /**
     *
     * @param Request $request
     * @param string $key
     * @Route("/remove/{key}", name="remove", requirements={"key": "[a-zA-Z0-9]+"})
     */
    public function remove(Request $request, $key)
    {
        $hash = $this->getHashFromKey($key);
        
        try {
            $this->getRedis()->transaction()
            ->del([
                'URL:HASH:'.$hash,
                'URL:HASH:'.$hash.':hits',
                'URL:ENC:'.$key,
            ])
            ->lrem('URL:ENC:HASH', 1, $key) // slow
            ->execute();
        }
        catch (\RedisException $re) {
            $this->getLogger()->error($re->getMessage());
        }
        
        return $this->redirectToRoute('home');
    }
}
