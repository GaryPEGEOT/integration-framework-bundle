<?php

namespace Smartbox\Integration\FrameworkBundle\Core\Processors\ControlFlow;

use Smartbox\CoreBundle\Type\SerializableArray;
use Smartbox\CoreBundle\Utils\Cache\CacheServiceInterface;
use Smartbox\Integration\FrameworkBundle\Core\Exchange;
use Smartbox\Integration\FrameworkBundle\Core\Messages\Traits\HasItinerary;
use Smartbox\Integration\FrameworkBundle\Core\Processors\Exceptions\RetryLaterException;
use Smartbox\Integration\FrameworkBundle\Core\Processors\Processor;
use Smartbox\Integration\FrameworkBundle\DependencyInjection\Traits\UsesCacheService;
use Smartbox\Integration\FrameworkBundle\DependencyInjection\Traits\UsesEvaluator;

class Throttler extends Processor{

    const CACHE_PREFIX = 'throttler_';

    use UsesEvaluator;
    use UsesCacheService;
    use HasItinerary;

    /**
     * @var int Time in milliseconds to reset the throttling limit
     */
    protected $periodMs = 60000;

    /**
     * @var string Symfony expression to determine the max amount of requests that can cross the throttler
     */
    protected $limitExpression;

    /**
     * @return boolean
     */
    public function isRuntimeBreakpoint()
    {
        return $this->runtimeBreakpoint;
    }

    /**
     * @param boolean $runtimeBreakpoint
     */
    public function setRuntimeBreakpoint($runtimeBreakpoint)
    {
        $this->runtimeBreakpoint = $runtimeBreakpoint;
    }

    /**
     * @return int
     */
    public function getPeriodMs()
    {
        return $this->periodMs;
    }

    /**
     * @param int $periodMs
     */
    public function setPeriodMs($periodMs)
    {
        $this->periodMs = $periodMs;
    }

    /**
     * @return string
     */
    public function getLimitExpression()
    {
        return $this->limitExpression;
    }

    /**
     * @param string $limitExpression
     */
    public function setLimitExpression($limitExpression)
    {
        $this->limitExpression = $limitExpression;
    }

    protected function getCacheKeyCount(){
        return self::CACHE_PREFIX.$this->id.'_count';
    }

    protected function getCacheKeyResetTime(){
        return self::CACHE_PREFIX.$this->id.'_reset';
    }

    /**
     * @param Exchange $exchange
     * @return boolean
     */
    protected function shouldPass(Exchange $exchange){
        $count = intval($this->cacheService->get($this->getCacheKeyCount()));
        $limit = $this->evaluator->evaluateWithExchange($this->limitExpression,$exchange);

        return $count < $limit;
    }

    protected function increaseCounter(){
        $count = intval($this->cacheService->get($this->getCacheKeyCount()));
        $this->cacheService->set($this->getCacheKeyCount(),$count+1);
    }

    protected function checkReset(){
        $reset = $this->cacheService->get($this->getCacheKeyResetTime());
        $currentTime = (int)(1000.0*microtime(true));

        if(!$reset || $currentTime >= intval($reset)){
            $newReset = $currentTime + $this->periodMs;
            $this->cacheService->set($this->getCacheKeyCount(),0);
            $this->cacheService->set($this->getCacheKeyResetTime(),$newReset);
        }
    }

    /**
     * @param Exchange $exchange
     * @param SerializableArray $processingContext
     *
     * @throws RetryLaterException
     */
    protected function doProcess(Exchange $exchange, SerializableArray $processingContext)
    {
        $this->checkReset();

        if(!$this->shouldPass($exchange)){
            $exception = new RetryLaterException("This message can't be processed because the throttling limit is reached in processor with id: ".$this->getId());
            $delaySeconds = (int) ($this->getPeriodMs()/1000);
            $exception->setDelay($delaySeconds);
            throw $exception;
        }else{
            $exchange->getItinerary()->prepend($this->itinerary);
            $this->increaseCounter();
        }
    }
}
