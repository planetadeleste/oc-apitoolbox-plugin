<?php

namespace PlanetaDelEste\ApiToolbox\Traits;

use System\Traits\EventEmitter;

/**
 * Emitter Trait
 *
 * @property string|null $mainEvent Main event name for the service
 *
 * @method string getModelClass()
 */
trait EmitterTrait
{
    use EventEmitter;

    /**
     * Get the domain name from the model class
     *
     * @return string
     */
    public function getDomainName(): string
    {
        $sModelClass = $this->getModelClass();

        return strtolower(class_basename($sModelClass));
    }

    /**
     * Fire an event before execution
     *
     * @param string $event
     * @param array  $params
     *
     * @return mixed
     */
    protected function fireBeforeEvent(string $event, array $params = []): mixed
    {
        return $this->emitEvent($event, 'before', $params);
    }

    /**
     * Fire an event after execution
     *
     * @param string $event
     * @param array  $params
     *
     * @return mixed
     */
    protected function fireAfterEvent(string $event, array $params = []): mixed
    {
        return $this->emitEvent($event, 'after', $params);
    }

    /**
     * @param string $event
     * @param string $sTiming
     * @param array  $params
     *
     * @return mixed
     */
    protected function emitEvent(string $event, string $sTiming, array $params = []): mixed
    {
        $sMethod = 'on'.ucfirst($sTiming).ucfirst($event);

        if (method_exists($this, $sMethod)) {
            $this->{$sMethod}(...$params);
        }

        $sDomain    = $this->getDomainName();
        $sMain      = !empty($this->mainEvent) ? "{$this->mainEvent}." : '';
        $sEventName = "planetadeleste.{$sDomain}.{$sMain}{$event}.{$sTiming}";

        // Fire local events (bindEvent)
        return $this->fireSystemEvent($sEventName, $params, false);
    }
}
