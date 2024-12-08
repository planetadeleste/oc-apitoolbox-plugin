<?php

namespace PlanetaDelEste\ApiToolbox\Classes\Helper;

use File;
use October\Rain\Support\Traits\Singleton;

/**
 * Class MeasureHelper
 *
 * @method static self instance()
 */
class MeasureHelper
{
    use Singleton;

    protected static $instance;

    /**
     * @var float|integer
     */
    protected float $start = 0;

    /**
     * @var float|integer
     */
    protected float $interval = 0;

    /**
     * @var integer
     */
    protected int $calls = 0;

    /**
     * @return int
     */
    public function calls(): int
    {
        return $this->calls;
    }

    /**
     * @return void
     */
    public function reset(): void
    {
        $this->start    = 0;
        $this->interval = 0;
        $this->calls    = 0;
    }

    /**
     * @param string $sTitle
     * @param        ...$params
     *
     * @return void
     */
    public function log(string $sTitle = 'Log', ...$params): void
    {
        if (!env('APP_MEASURE') || !env('APP_DEBUG') || app()->environment('production')) {
            return;
        }

        $fSecs  = $this->stop();
        $sTitle = vsprintf($sTitle, array_wrap($params));
        $sTitle = sprintf('%s - call #%s in %s secs', $sTitle, $this->calls, $fSecs);
        trace_log($sTitle);
//        $sPath  = storage_path('logs/measure.log');
//        File::append($sPath, $sTitle.PHP_EOL);
    }

    /**
     * @return float
     */
    public function interval(): float
    {
        $this->calls++;

        if (!$fInterval = $this->interval) {
            $this->start();
            $fInterval = $this->start;
        }

        $this->interval = microtime(true);

        return round($this->interval - $fInterval, 4);
    }

    /**
     * @return void
     */
    public function start(): void
    {
        $this->start    = microtime(true);
        $this->interval = microtime(true);
        $this->calls    = 1;

//        $sPath = storage_path('logs/measure.log');
//        $sContent = '# INIT MEASURE #'.PHP_EOL;
//        File::put($sPath, $sContent);
    }

    /**
     * Calculates the time elapsed since the start of the measurement and returns it as a float.
     *
     * @return float The time elapsed since the start of the measurement in seconds.
     */
    public function stop(): float
    {
        $this->calls++;

        return round(microtime(true) - $this->start, 4);
    }
}
