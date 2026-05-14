<?php
declare(strict_types=1);

namespace TinyCompiler;

final class FuncDecl implements Stmt
{
    /** @param string[] $params */
    public function __construct(public string $name, public array $params, public BlockStmt $body)
    {
    }

    public function kind(): string
    {
        return 'FuncDecl';
    }
}
