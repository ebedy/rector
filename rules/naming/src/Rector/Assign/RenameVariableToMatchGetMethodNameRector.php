<?php

declare(strict_types=1);

namespace Rector\Naming\Rector\Assign;

use PhpParser\Node;
use PhpParser\Node\Expr\ArrowFunction;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\FunctionLike;
use Rector\Core\Rector\AbstractRector;
use Rector\Core\RectorDefinition\CodeSample;
use Rector\Core\RectorDefinition\RectorDefinition;
use Rector\Naming\Naming\ExpectedNameResolver;

/**
 * @see \Rector\Naming\Tests\Rector\Assign\RenameVariableToMatchGetMethodNameRector\RenameVariableToMatchGetMethodNameRectorTest
 */
final class RenameVariableToMatchGetMethodNameRector extends AbstractRector
{
    /**
     * @var ExpectedNameResolver
     */
    private $expectedNameResolver;

    public function __construct(ExpectedNameResolver $expectedNameResolver)
    {
        $this->expectedNameResolver = $expectedNameResolver;
    }

    public function getDefinition(): RectorDefinition
    {
        return new RectorDefinition('Rename variable to match get method name', [
            new CodeSample(
                <<<'PHP'
class SomeClass {
    public function run()
    {
        $a = $this->getRunner();
    }
}
PHP
,
                <<<'PHP'
class SomeClass {
    public function run()
    {
        $runner = $this->getRunner();
    }
}
PHP
            ),
        ]);
    }

    /**
     * @return string[]
     */
    public function getNodeTypes(): array
    {
        return [Assign::class];
    }

    /**
     * @param Assign $node
     */
    public function refactor(Node $node): ?Node
    {
        if (
            $node->expr instanceof ArrowFunction || ! $node->var instanceof Variable
        ) {
            return null;
        }

        $newName = $this->expectedNameResolver->resolveForGetCallExpr($node->expr);
        if ($newName === null || $this->isName($node, $newName)) {
            return null;
        }

        $skip = $this->skipOnConflict($node, $newName);
        if ($skip) {
            return null;
        }

        $this->renameVariable($node, $newName);

        return $node;
    }

    private function renameVariable(Node $node, string $newName): void
    {
        $parentNodeStmts = $this->getParentNodeStmts($node);

        /** @var Variable $variableNode */
        $variableNode = $node->var;

        /** @var string $originalName */
        $originalName = $variableNode->name;

        $this->traverseNodesWithCallable($parentNodeStmts, function (Node $node) use (
            $originalName,
            $newName
        ): void {
            /** @var Variable $node */
            if ($this->isVariableName($node, $originalName)) {
                $node->name = $newName;
            }
        });
    }

    private function getParentNodeStmts(Node $node): array
    {
        /** @var FunctionLike|null $parentNode */
        $parentNode = $this->betterNodeFinder->findFirstParentInstanceOf($node, FunctionLike::class);
        if ($parentNode === null) {
            return [];
        }

        return $parentNode->getStmts() ?? [];
    }

    private function skipOnConflict(Node $node, string $newName): bool
    {
        $parentNodeStmts = $this->getParentNodeStmts($node);

        $skip = false;
        $this->traverseNodesWithCallable($parentNodeStmts, function (Node $node) use ($newName, &$skip): void {
            /** @var Variable $node */
            if ($this->isVariableName($node, $newName)) {
                $skip = true;
            }
        });

        return $skip;
    }
}
