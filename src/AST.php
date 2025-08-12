<?php
declare(strict_types=1);

namespace TinyCompiler;

require_once __DIR__ . '/Token.php';

interface Node
{
    public function kind(): string;
}

interface Stmt extends Node
{
}

interface Expr extends Node
{
}

final class Program implements Node
{
    /** @param Stmt[] $stmts */
    public function __construct(public array $stmts)
    {
    }

    public function kind(): string
    {
        return 'Program';
    }
}

final class BlockStmt implements Stmt
{
    /** @param Stmt[] $stmts */
    public function __construct(public array $stmts)
    {
    }

    public function kind(): string
    {
        return 'BlockStmt';
    }
}

final class LetStmt implements Stmt
{
    public function __construct(public string $name, public ?Expr $init)
    {
    }

    public function kind(): string
    {
        return 'LetStmt';
    }
}

final class ConstStmt implements Stmt
{
    public function __construct(public string $name, public Expr $init)
    {
    }

    public function kind(): string
    {
        return 'ConstStmt';
    }
}

final class ExprStmt implements Stmt
{
    public function __construct(public Expr $expr)
    {
    }

    public function kind(): string
    {
        return 'ExprStmt';
    }
}

final class IfStmt implements Stmt
{
    public function __construct(public Expr $cond, public Stmt $then, public ?Stmt $else)
    {
    }

    public function kind(): string
    {
        return 'IfStmt';
    }
}

final class WhileStmt implements Stmt
{
    public function __construct(public Expr $cond, public Stmt $body)
    {
    }

    public function kind(): string
    {
        return 'WhileStmt';
    }
}

final class ForStmt implements Stmt
{
    public function __construct(public ?Stmt $init, public ?Expr $cond, public ?Expr $step, public Stmt $body)
    {
    }

    public function kind(): string
    {
        return 'ForStmt';
    }
}

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

final class FunDecl implements Stmt
{
    /** @param string[] $params */
    public function __construct(public string $name, public array $params, public BlockStmt $body)
    {
    }

    public function kind(): string
    {
        return 'FunDecl';
    }
}

final class Ident implements Expr
{
    public function __construct(public string $name)
    {
    }

    public function kind(): string
    {
        return 'Ident';
    }
}

final class NumberLiteral implements Expr
{
    public function __construct(public string $raw)
    {
    }

    public function kind(): string
    {
        return 'NumberLiteral';
    }
}

final class StringLiteral implements Expr
{
    public function __construct(public string $value)
    {
    }

    public function kind(): string
    {
        return 'StringLiteral';
    }
}

final class BoolLiteral implements Expr
{
    public function __construct(public bool $value)
    {
    }

    public function kind(): string
    {
        return 'BoolLiteral';
    }
}

final class NullLiteral implements Expr
{
    public function __construct()
    {
    }

    public function kind(): string
    {
        return 'NullLiteral';
    }
}

final class ArrayLiteral implements Expr
{
    /** @param Expr[] $elements */
    public function __construct(public array $elements)
    {
    }

    public function kind(): string
    {
        return 'ArrayLiteral';
    }
}

final class IndexExpr implements Expr
{
    public function __construct(public Expr $array, public Expr $index)
    {
    }

    public function kind(): string
    {
        return 'IndexExpr';
    }
}

final class UnaryExpr implements Expr
{
    public function __construct(public string $op, public Expr $expr)
    {
    }

    public function kind(): string
    {
        return 'UnaryExpr';
    }
}

final class BinaryExpr implements Expr
{
    public function __construct(public string $op, public Expr $left, public Expr $right)
    {
    }

    public function kind(): string
    {
        return 'BinaryExpr';
    }
}

final class AssignExpr implements Expr
{
    public function __construct(public Expr $left, public Expr $right)
    {
    }

    public function kind(): string
    {
        return 'AssignExpr';
    }
}

final class CallExpr implements Expr
{
    /** @param Expr[] $args */
    public function __construct(public Expr $callee, public array $args)
    {
    }

    public function kind(): string
    {
        return 'CallExpr';
    }
}
