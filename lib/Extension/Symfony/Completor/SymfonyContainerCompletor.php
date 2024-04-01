<?php

namespace Phpactor\Extension\Symfony\Completor;

use Generator;
use Microsoft\PhpParser\Node;
use Microsoft\PhpParser\Node\Expression\CallExpression;
use Microsoft\PhpParser\Node\Expression\MemberAccessExpression;
use Microsoft\PhpParser\Node\QualifiedName;
use Microsoft\PhpParser\Node\StringLiteral;
use Phpactor\Completion\Bridge\TolerantParser\TolerantCompletor;
use Phpactor\Completion\Core\Suggestion;
use Phpactor\Extension\Symfony\Model\SymfonyContainerInspector;
use Phpactor\Extension\Symfony\Model\SymfonyContainerService;
use Phpactor\Extension\Symfony\WorseReflection\SymfonyContainerContextResolver;
use Phpactor\TextDocument\ByteOffset;
use Phpactor\TextDocument\TextDocument;
use Phpactor\WorseReflection\Core\TypeFactory;
use Phpactor\WorseReflection\Core\Util\NodeUtil;
use Phpactor\WorseReflection\Reflector;

class SymfonyContainerCompletor implements TolerantCompletor
{
    public function __construct(private Reflector $reflector, private SymfonyContainerInspector $inspector)
    {
    }

    public function complete(Node $node, TextDocument $source, ByteOffset $offset): Generator
    {
        $inQuote = false;
        if ($node instanceof StringLiteral && $node->parent->parent) {
            $inQuote = true;
            $node = $node->getFirstAncestor(CallExpression::class);
        }
        if ($node instanceof QualifiedName) {
            $node = $node->getFirstAncestor(CallExpression::class);
        }

        if (!$node instanceof CallExpression) {
            return;
        }

        $memberAccess = $node->callableExpression;

        if (!$memberAccess instanceof MemberAccessExpression) {
            return;
        }

        $methodName = NodeUtil::nameFromTokenOrNode($node, $memberAccess->memberName);

        if (!\in_array($methodName, [...SymfonyContainerContextResolver::CONTAINER_SERVICE_METHODS, ...SymfonyContainerContextResolver::CONTAINER_PARAMETER_METHODS], true)) {
            return;
        }

        $expression = $memberAccess->dereferencableExpression;
        $containerType = $this->reflector->reflectOffset($source, $expression->getEndPosition())->nodeContext()->type();

        // If the Symfony container is called with a service method, do the service related logic
        if (
            \in_array($methodName, SymfonyContainerContextResolver::CONTAINER_SERVICE_METHODS, true)
              && $containerType->instanceof(TypeFactory::class(SymfonyContainerContextResolver::CONTAINER_CLASS))->isTrue()) {

            foreach ($this->inspector->services() as $service) {
                $label = $service->id;
                $suggestion = $inQuote ? $service->id : sprintf('\'%s\'', $service->id);
                $import = null;

                if ($this->serviceIdIsFqn($service) && $inQuote) {
                    continue;
                }

                if (false === $this->serviceIdIsFqn($service) && false === $inQuote) {
                    continue;
                }

                if (false === $inQuote && $this->serviceIdIsFqn($service)) {
                    $suggestion = $service->type->short() . '::class';
                    $label = $service->type->short() . '::class';
                    $import = $service->type->__toString();
                }

                yield Suggestion::createWithOptions($suggestion, [
                    'label' => $label,
                    'short_description' => $service->id,
                    'documentation' => sprintf('**Symfony Service**: %s', $service->type->__toString()),
                    'type' => Suggestion::TYPE_VALUE,
                    'name_import' => $import,
                    'priority' => 555,
                ]);
            }
        }

        // If the Symfony container is callled with a parameter method, or the ParameterBag is called, do the parameter logic
        if (\in_array($methodName, SymfonyContainerContextResolver::CONTAINER_PARAMETER_METHODS, true)
          || $containerType->instanceof(TypeFactory::class(SymfonyContainerContextResolver::PARAMETER_BAG_CLASS))->isTrue()) {
            foreach ($this->inspector->parameters() as $parameter) {
                $label = $parameter->id;
                $suggestion = $inQuote ? $parameter->id : sprintf('\'%s\'', $parameter->id);
                $import = null;

                yield Suggestion::createWithOptions($suggestion, [
                    'label' => $label,
                    'short_description' => $parameter->id,
                    'documentation' => sprintf('**Symfony Parameter**: %s', $parameter->type->__toString()),
                    'type' => Suggestion::TYPE_VALUE,
                    'name_import' => $import,
                    'priority' => 555,
                ]);
            }
        }

        return true;
    }

    private function serviceIdIsFqn(SymfonyContainerService $service): bool
    {
        return $service->type->isClass() && $service->id === $service->type->__toString();
    }
}
