<?php

namespace Phpactor\Extension\Symfony\WorseReflection;

use Phpactor\Extension\Symfony\Model\SymfonyContainerInspector;
use Phpactor\WorseReflection\Core\ClassName;
use Phpactor\WorseReflection\Core\Inference\FunctionArguments;
use Phpactor\WorseReflection\Core\Inference\Resolver\MemberAccess\MemberContextResolver;
use Phpactor\WorseReflection\Core\Reflection\ReflectionMember;
use Phpactor\WorseReflection\Core\Type;
use Phpactor\WorseReflection\Core\TypeFactory;
use Phpactor\WorseReflection\Core\Type\ClassStringType;
use Phpactor\WorseReflection\Core\Type\ClassType;
use Phpactor\WorseReflection\Core\Type\StringLiteralType;
use Phpactor\WorseReflection\Reflector;

class SymfonyContainerContextResolver implements MemberContextResolver
{
    public const CONTAINER_CLASS = 'Symfony\\Component\\DependencyInjection\\ContainerInterface';
    public const PARAMETER_BAG_CLASS = 'Symfony\\Component\\DependencyInjection\\ParameterBag\\ParameterBagInterface';
    public const CONTAINER_SERVICE_METHODS = ['set', 'get', 'has', 'initialized'];
    public const CONTAINER_PARAMETER_METHODS = ['setParameter', 'getParameter', 'hasParameter'];

    public function __construct(private SymfonyContainerInspector $inspector)
    {
    }

    public function resolveMemberContext(
        Reflector $reflector,
        ReflectionMember $member,
        Type $type,
        ?FunctionArguments $arguments
    ): ?Type {
        if ($member->memberType() !== ReflectionMember::TYPE_METHOD) {
            return null;
        }

        if (!\in_array($member->name(), [...self::CONTAINER_SERVICE_METHODS, ...self::CONTAINER_PARAMETER_METHODS], true)) {
            return null;
        }

        if (count($arguments) === 0) {
            return null;
        }

        $resolveAsParameter = false;
        if ($member->class()->isInstanceOf(ClassName::fromString(self::CONTAINER_CLASS))) {
            // If the Symfony container is called with a parameter method, skip the service logic
            if (\in_array($member->name(), self::CONTAINER_PARAMETER_METHODS, true)) {
                $resolveAsParameter = true;
            } else {
                $argument = $arguments->at(0)->type();
                if ($argument instanceof StringLiteralType) {
                    $service = $this->inspector->service($argument->value());
                    if (null === $service) {
                        return TypeFactory::union(TypeFactory::object(), TypeFactory::null());
                    }
                    return $service->type;
                }
                if ($argument instanceof ClassStringType && $argument->className()) {
                    $service = $this->inspector->service($argument->className()->__toString());
                    if (null === $service) {
                        return TypeFactory::union(TypeFactory::object(), TypeFactory::null());
                    }
                    $type = $service->type;
                    if ($type instanceof ClassType) {
                        $type = $type->asReflectedClasssType($reflector);
                    }
                    return $type;
                }
            }
        }

        if ($resolveAsParameter || $member->class()->isInstanceOf(ClassName::fromString(self::PARAMETER_BAG_CLASS))) {
            $argument = $arguments->at(0)->type();
            if ($argument instanceof StringLiteralType) {
                $parameter = $this->inspector->parameter($argument->value());

                if (null === $parameter) {
                    return TypeFactory::union(TypeFactory::object(), TypeFactory::null());
                }

                return $parameter->type;
            }
        }

        return TypeFactory::undefined();
    }
}
