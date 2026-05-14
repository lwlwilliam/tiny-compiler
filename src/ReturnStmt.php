<?php
declare(strict_types=1);

namespace TinyCompiler;

final class ReturnStmt implements Stmt
{
    public function __construct(public ?Expr $value)
    {
    }

    public function kind(): string
    {
        return 'ReturnStmt';
    }
}
