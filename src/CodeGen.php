<?php
declare(strict_types=1);

namespace TinyCompiler;

final class CodeGen
{
    private array $consts = [];
    /** @var array<string,int> */
    private array $constIndex = [];
    private SymbolTable $sym;
    /** @var array<string,FunctionBC> */
    private array $functions = [];
    /** @var array<int, array{breakPatches: int[], continuePatches: int[], continueTarget: int}> */
    private array $loopStack = [];

    public function __construct()
    {
        $this->sym = new SymbolTable();
    }

    /**
     * @throws CGError
     */
    public function emitModule(Program $prog): ModuleBC
    {
        $entry = $this->emitEntry($prog);
        $globalsMap = [];
        foreach ($this->sym->globals as $name => $meta) {
            $globalsMap[$name] = $meta['index'];
        }
        return new ModuleBC($this->consts, $globalsMap, $this->functions, $entry);
    }

    /**
     * @throws CGError
     */
    private function emitEntry(Program $prog): array
    {
        $code = [];
        $this->walkStmts($prog->stmts, function ($s) {
            if ($s instanceof FuncDecl) {
                $this->sym->setGlobalMut($s->name, true);
            }
        });
        $this->walkStmts($prog->stmts, function ($s) {
            if ($s instanceof FuncDecl) {
                $this->emitFunction($s);
            }
        });
        foreach ($prog->stmts as $s) {
            if (!($s instanceof FuncDecl)) {
                $this->emitStmt($code, $s, null);
            }
        }
        $code[] = Op::HALT;
        return $code;
    }

    /**
     * @param Stmt[] $stmts
     * @param callable $cb function(Stmt $s): void
     */
    private function walkStmts(array $stmts, callable $cb): void
    {
        foreach ($stmts as $s) {
            $cb($s);
            if ($s instanceof BlockStmt) {
                $this->walkStmts($s->stmts, $cb);
            } elseif ($s instanceof IfStmt) {
                $this->walkStmts([$s->then], $cb);
                if ($s->else) {
                    $this->walkStmts([$s->else], $cb);
                }
            } elseif ($s instanceof WhileStmt) {
                $this->walkStmts([$s->body], $cb);
            } elseif ($s instanceof ForStmt) {
                if ($s->init) {
                    $this->walkStmts([$s->init], $cb);
                }
                $this->walkStmts([$s->body], $cb);
            }
        }
    }

    private function emitFunction(FuncDecl $f): void
    {
        $prevSym = $this->sym;
        $this->sym = new SymbolTable();
        foreach ($f->params as $p) {
            $this->sym->defineLocal($p, false);
        }
        $code = [];
        $this->emitBlock($code, $f->body, $this->sym);
        $kNull = $this->internConst(null);
        $code[] = Op::CONST_;
        $code[] = $kNull;
        $code[] = Op::RET;
        $this->functions[$f->name] = new FunctionBC($code, $this->sym->localCount);
        $this->sym = $prevSym;
    }

    /**
     * @throws CGError
     */
    private function emitBlock(array &$code, BlockStmt $b, ?SymbolTable $fnScope): void
    {
        foreach ($b->stmts as $s) {
            $this->emitStmt($code, $s, $fnScope);
        }
    }

    /**
     * @throws CGError
     */
    private function emitStmt(array &$code, Stmt $s, ?SymbolTable $fnScope): void
    {
        if ($s instanceof BlockStmt) {
            $this->emitBlock($code, $s, $fnScope);
            return;
        }
        if ($s instanceof LetStmt) {
            $idx = $fnScope ? $fnScope->defineLocal($s->name, false) : $this->sym->defineGlobal($s->name, false);
            if ($s->init !== null) {
                $this->emitExpr($code, $s->init, $fnScope);
            } else {
                $kNull = $this->internConst(null);
                $code[] = Op::CONST_;
                $code[] = $kNull;
            }
            if ($fnScope) {
                $code[] = Op::STORE_LOCAL;
                $code[] = $idx;
            } else {
                $code[] = Op::STORE_GLOBAL;
                $code[] = $idx;
            }
            $code[] = Op::POP;
            return;
        }
        if ($s instanceof ConstStmt) {
            $idx = $fnScope ? $fnScope->defineLocal($s->name, true) : $this->sym->setGlobalMut($s->name, true);
            $this->emitExpr($code, $s->init, $fnScope);
            if ($fnScope) {
                $code[] = Op::STORE_LOCAL;
                $code[] = $idx;
            } else {
                $code[] = Op::STORE_GLOBAL;
                $code[] = $idx;
            }
            $code[] = Op::POP;
            return;
        }
        if ($s instanceof ExprStmt) {
            $this->emitExpr($code, $s->expr, $fnScope);
            $code[] = Op::POP;
            return;
        }
        if ($s instanceof IfStmt) {
            $this->emitExpr($code, $s->cond, $fnScope);
            $code[] = Op::JMP_IF_FALSE;
            $jFalse = count($code);
            $code[] = -1;
            $code[] = Op::POP;
            $this->emitStmt($code, $s->then, $fnScope);
            if ($s->else !== null) {
                $code[] = Op::JMP;
                $jEnd = count($code);
                $code[] = -1;
                $code[$jFalse] = count($code);
                $code[] = Op::POP;
                $this->emitStmt($code, $s->else, $fnScope);
                $code[$jEnd] = count($code);
            } else {
                $code[] = Op::JMP;
                $jEnd = count($code);
                $code[] = -1;
                $code[$jFalse] = count($code);
                $code[] = Op::POP;
                $code[$jEnd] = count($code);
            }
            return;
        }
        if ($s instanceof WhileStmt) {
            $start = count($code);
            $this->emitExpr($code, $s->cond, $fnScope);
            $code[] = Op::JMP_IF_FALSE;
            $jExit = count($code);
            $code[] = -1;
            $code[] = Op::POP;
            $this->loopStack[] = ['breakPatches' => [], 'continuePatches' => [], 'continueTarget' => $start];
            $this->emitStmt($code, $s->body, $fnScope);
            $ctx = array_pop($this->loopStack);
            $code[] = Op::JMP;
            $code[] = $start;
            $breakTarget = count($code);
            $code[$jExit] = $breakTarget;
            foreach ($ctx['breakPatches'] as $patchIdx) {
                $code[$patchIdx] = $breakTarget;
            }
            foreach ($ctx['continuePatches'] as $patchIdx) {
                $code[$patchIdx] = $ctx['continueTarget'];
            }
            $code[] = Op::POP;
            return;
        }
        if ($s instanceof ForStmt) {
            if ($s->init) {
                $this->emitStmt($code, $s->init, $fnScope);
            }
            $start = count($code);
            if ($s->cond) {
                $this->emitExpr($code, $s->cond, $fnScope);
            } else {
                $kTrue = $this->internConst(true);
                $code[] = Op::CONST_;
                $code[] = $kTrue;
            }
            $code[] = Op::JMP_IF_FALSE;
            $jExit = count($code);
            $code[] = -1;
            $code[] = Op::POP;
            // continue target is set after body (to step expression or condition)
            $this->loopStack[] = ['breakPatches' => [], 'continuePatches' => [], 'continueTarget' => -1];
            $this->emitStmt($code, $s->body, $fnScope);
            $ctx = array_pop($this->loopStack);
            $continueTarget = count($code);
            if ($s->step) {
                $this->emitExpr($code, $s->step, $fnScope);
                $code[] = Op::POP;
            }
            $code[] = Op::JMP;
            $code[] = $start;
            $breakTarget = count($code);
            $code[$jExit] = $breakTarget;
            foreach ($ctx['breakPatches'] as $patchIdx) {
                $code[$patchIdx] = $breakTarget;
            }
            foreach ($ctx['continuePatches'] as $patchIdx) {
                $code[$patchIdx] = $continueTarget;
            }
            $code[] = Op::POP;
            return;
        }
        if ($s instanceof BreakStmt) {
            if (empty($this->loopStack)) {
                throw new CGError('break statement outside of loop');
            }
            $ctx = &$this->loopStack[count($this->loopStack) - 1];
            $code[] = Op::JMP;
            $ctx['breakPatches'][] = count($code);
            $code[] = -1;
            return;
        }
        if ($s instanceof ContinueStmt) {
            if (empty($this->loopStack)) {
                throw new CGError('continue statement outside of loop');
            }
            $ctx = &$this->loopStack[count($this->loopStack) - 1];
            $code[] = Op::JMP;
            $ctx['continuePatches'][] = count($code);
            $code[] = -1;
            return;
        }
        if ($s instanceof ReturnStmt) {
            if ($fnScope === null) {
                throw new CGError('expect return used in function');
            }
            if ($s->value) {
                $this->emitExpr($code, $s->value, $fnScope);
            } else {
                $kNull = $this->internConst(null);
                $code[] = Op::CONST_;
                $code[] = $kNull;
            }
            $code[] = Op::RET;
            return;
        }
        if ($s instanceof FuncDecl) {
            return;
        }
        throw new CGError('expect handle statement: ' . $s->kind());
    }

    /**
     * @throws CGError
     */
    private function emitExpr(array &$code, Expr $e, ?SymbolTable $fnScope): void
    {
        if ($e instanceof NumberLiteral) {
            $this->emitConst($code, $this->parseNumber($e->raw));
            return;
        }
        if ($e instanceof StringLiteral) {
            $this->emitConst($code, $e->value);
            return;
        }
        if ($e instanceof BoolLiteral) {
            $this->emitConst($code, $e->value);
            return;
        }
        if ($e instanceof NullLiteral) {
            $this->emitConst($code, null);
            return;
        }
        if ($e instanceof ArrayLiteral) {
            foreach ($e->elements as $el) {
                $this->emitExpr($code, $el, $fnScope);
            }
            $code[] = Op::ARRAY_NEW;
            $code[] = count($e->elements);
            return;
        }
        if ($e instanceof Ident) {
            $sym = $fnScope?->lookup($e->name) ?? $this->sym->lookup($e->name);
            if (!$sym) {
                $k = $this->internConst($e->name);
                $code[] = Op::CONST_;
                $code[] = $k;
                return;
            }
            if ($sym['scope'] === 'local') {
                $code[] = Op::LOAD_LOCAL;
                $code[] = $sym['index'];
            } else {
                $code[] = Op::LOAD_GLOBAL;
                $code[] = $sym['index'];
            }
            return;
        }
        if ($e instanceof IndexExpr) {
            $this->emitExpr($code, $e->array, $fnScope);
            $this->emitExpr($code, $e->index, $fnScope);
            $code[] = Op::ARRAY_GET;
            return;
        }
        if ($e instanceof UnaryExpr) {
            $this->emitExpr($code, $e->expr, $fnScope);
            if ($e->op === '-') {
                $code[] = Op::NEG;
                return;
            }
            if ($e->op === '!') {
                $code[] = Op::NOT;
                return;
            }
            throw new CGError('unknown unary operator: ' . $e->op);
        }
        if ($e instanceof BinaryExpr) {
            if ($e->op === '&&' || $e->op === '||') {
                $this->emitExpr($code, $e->left, $fnScope);
                if ($e->op === '&&') {
                    $code[] = Op::JMP_IF_FALSE;
                    $jFalse = count($code);
                    $code[] = -1;
                    $code[] = Op::POP;
                    $this->emitExpr($code, $e->right, $fnScope);
                    $code[$jFalse] = count($code);
                } else {
                    $code[] = Op::JMP_IF_FALSE;
                    $jFalse = count($code);
                    $code[] = -1;
                    $code[] = Op::JMP;
                    $jEnd = count($code);
                    $code[] = -1;
                    $code[$jFalse] = count($code);
                    $code[] = Op::POP;
                    $this->emitExpr($code, $e->right, $fnScope);
                    $code[$jEnd] = count($code);
                }
                return;
            }
            $this->emitExpr($code, $e->left, $fnScope);
            $this->emitExpr($code, $e->right, $fnScope);
            $map = [
                '+' => Op::ADD, '-' => Op::SUB, '*' => Op::MUL, '/' => Op::DIV, '%' => Op::MOD,
                '==' => Op::EQ, '!=' => Op::NE, '<' => Op::LT, '<=' => Op::LE, '>' => Op::GT, '>=' => Op::GE,
            ];
            if (!isset($map[$e->op])) {
                throw new CGError('unknown binary operator: ' . $e->op);
            }
            $code[] = $map[$e->op];
            return;
        }
        if ($e instanceof AssignExpr) {
            if ($e->left instanceof Ident) {
                $sym = $fnScope?->lookup($e->left->name) ?? $this->sym->lookup($e->left->name);
                if (!$sym) {
                    throw new CGError('undefined variable: ' . $e->left->name);
                }
                if ($sym['isConst']) {
                    throw new CGError('can not reassign to constant: ' . $e->left->name);
                }
                $this->emitExpr($code, $e->right, $fnScope);
                if ($sym['scope'] === 'local') {
                    $code[] = Op::STORE_LOCAL;
                    $code[] = $sym['index'];
                } else {
                    $code[] = Op::STORE_GLOBAL;
                    $code[] = $sym['index'];
                }
                return;
            }
            if ($e->left instanceof IndexExpr) {
                if (!($e->left->array instanceof Ident)) {
                    throw new CGError('left side of index assignment must be a variable');
                }
                $arrName = $e->left->array->name;
                $sym = $fnScope?->lookup($arrName) ?? $this->sym->lookup($arrName);
                if (!$sym) {
                    throw new CGError('undefined variable: ' . $arrName);
                }
                if ($sym['isConst']) {
                    throw new CGError('can not reassign to constant: ' . $arrName);
                }
                $this->emitExpr($code, $e->left->array, $fnScope);
                $this->emitExpr($code, $e->left->index, $fnScope);
                $this->emitExpr($code, $e->right, $fnScope);
                $code[] = Op::ARRAY_SET;
                if ($sym['scope'] === 'local') {
                    $code[] = Op::STORE_LOCAL;
                    $code[] = $sym['index'];
                } else {
                    $code[] = Op::STORE_GLOBAL;
                    $code[] = $sym['index'];
                }
                $code[] = Op::POP;
                return;
            }
            throw new CGError('invalid assignment lvalue');
        }
        if ($e instanceof CallExpr) {
            if ($e->callee instanceof Ident) {
                $kName = $this->internConst($e->callee->name);
                foreach ($e->args as $a) {
                    $this->emitExpr($code, $a, $fnScope);
                }
                $code[] = Op::CALL_NAME;
                $code[] = $kName;
                $code[] = count($e->args);
                return;
            }
            $this->emitExpr($code, $e->callee, $fnScope);
            $nameTempConst = $this->internConst('__call_dynamic');
            foreach ($e->args as $a) {
                $this->emitExpr($code, $a, $fnScope);
            }
            $code[] = Op::CALL_NAME;
            $code[] = $nameTempConst;
            $code[] = count($e->args);
            return;
        }
        throw new CGError('expect handle expression: ' . $e->kind());
    }

    private function emitConst(array &$code, mixed $v): void
    {
        $k = $this->internConst($v);
        $code[] = Op::CONST_;
        $code[] = $k;
    }

    private function internConst(mixed $v): int
    {
        $key = $this->constKey($v);
        if (isset($this->constIndex[$key])) {
            return $this->constIndex[$key];
        }
        $idx = count($this->consts);
        $this->consts[] = $v;
        $this->constIndex[$key] = $idx;
        return $idx;
    }

    private function constKey(mixed $v): string
    {
        return serialize($v);
    }

    private function parseNumber(string $raw): int|float
    {
        return str_contains($raw, '.') ? (float)$raw : (int)$raw;
    }
}
