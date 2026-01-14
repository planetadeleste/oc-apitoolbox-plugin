<?php

namespace PlanetaDelEste\ApiToolbox\Contracts;

interface ControllerDomainInterface
{
    /**
     * Get the domain name
     *
     * @return string
     */
    public function getDomain(): string;

    /**
     * Set the domain name
     *
     * @param string $domain
     * @return $this
     */
    public function setDomain(string $domain);

    /**
     * Check if domain is valid
     *
     * @return bool
     */
    public function isValidDomain(): bool;
}
