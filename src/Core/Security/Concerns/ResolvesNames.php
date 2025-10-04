<?php

namespace Timeax\FortiPlugin\Core\Security\Concerns;

use PhpParser\Node;
use PhpParser\Node\Name;
use PhpParser\Node\Identifier;

trait ResolvesNames
{
    /** Normalize: drop leading "\"; keep the case as-is (callers can strtolower). */
    protected function normClass(string $name): string
    {
        return ltrim($name, '\\');
    }

    /** Prefer NameResolver’s resolvedName/namespacedName when present. */
    protected function fqNameOf(mixed $node): ?string
    {
        if ($node instanceof Name) {
            $resolved = $node->getAttribute('resolvedName');
            if ($resolved instanceof Name) {
                return $this->normClass($resolved->toString());
            }
            // if replaceNodes=true, this is already FullyQualified
            return $this->normClass($node->toString());
        }
        if ($node instanceof Identifier) {
            return $this->normClass($node->toString());
        }
        if (is_string($node)) {
            return $this->normClass($node);
        }
        return null;
    }

    /** For class declarations (NameResolver sets ->namespacedName). */
    protected function declFqcn(Node\Stmt\Class_ $class): ?string
    {
        if (isset($class->namespacedName)) {
            return $this->normClass($class->namespacedName->toString());
        }
        return $class->name?->toString();
    }

    /** For function declarations (NameResolver sets ->namespacedName). */
    protected function declFuncName(Node\Stmt\Function_ $fn): ?string
    {
        if (isset($fn->namespacedName)) {
            return $this->normClass($fn->namespacedName->toString());
        }
        return $fn->name->toString();
    }

    /**
     * Resolve self/static/parent/FQCN to a class key.
     * Return lower-case key when you plan to use it as a map index.
     */
    protected function resolveStaticClassRef(Name|Identifier|string $classNode, string $currentClassKey): ?string
    {
        $raw = $this->fqNameOf($classNode);
        if ($raw === null) return null;

        $lc = strtolower($raw);
        if ($lc === 'self' || $lc === 'static' || $lc === 'parent') {
            return $currentClassKey;
        }
        return strtolower($raw);
    }
}

