<?php

namespace Phpactor\Extension\Symfony\Model;

class InMemorySymfonyContainerInspector implements SymfonyContainerInspector
{
    /**
     * @param SymfonyContainerService[] $services
     * @param SymfonyContainerParameter[] $parameters
     */
    public function __construct(private array $services, private array $parameters)
    {
    }

    public function services(): array
    {
        return $this->services;
    }

    public function parameters(): array
    {
        return $this->parameters;
    }

    public function service(string $id): ?SymfonyContainerService
    {
        foreach ($this->services as $service) {
            if ($service->id === $id) {
                return $service;
            }
        }

        return null;
    }

    public function parameter(string $id): ?SymfonyContainerParameter
    {

        foreach ($this->parameters as $parameter) {
            if ($parameter->id === $id) {
                return $parameter;
            }
        }

        return null;
    }
}
