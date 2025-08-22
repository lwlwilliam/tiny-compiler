<?php
declare(strict_types=1);

namespace TinyCompiler;

require_once __DIR__ . '/AST.php';
require_once __DIR__ . '/Op.php';

final class CGError extends \Exception
{
}

final class FunctionBC
{
    /** @param int[] $code */
    public function __construct(public array $code, public int $nLocals)
    {
    }
}

final class ModuleBC
{
    /**
     * @param array<int, mixed> $consts
     * @param array<string, int> $globals
     * @param array<string, FunctionBC> $functions
     * @param int[] $entry
     */
    public function __construct(
        public array $consts,
        public array $globals,
        public array $functions,
        public array $entry,
    )
    {
    }
}

final class SymbolTable
{
    /** @var array<string, array{index:int,isConst:bool}> */
    public array $globals = [];
    /** @var array<string, array{index:int,isConst:bool}> */
    public array $locals = [];
    public int $localCount = 0;

    public function defineGlobal(string $name, bool $isConst): int
    {
        if (isset($this->globals[$name])) {
            return $this->globals[$name]['index'];
        }
        $idx = count($this->globals);
        $this->globals[$name] = ['index' => $idx, 'isConst' => $isConst];
        return $idx;
    }

    /**
     * @throws CGError
     */
    public function setGlobalMut(string $name, bool $isConst): int
    {
        if (isset($this->globals[$name])) {
            throw new CGError("duplicated global define: $name");
        }
        $idx = count($this->globals);
        $this->globals[$name] = ['index' => $idx, 'isConst' => $isConst];
        return $idx;
    }

    public function defineLocal(string $name, bool $isConst): int
    {
        if (isset($this->locals[$name])) {
            return $this->locals[$name]['index'];
        }
        $idx = $this->localCount++;
        $this->locals[$name] = ['index' => $idx, 'isConst' => $isConst];
        return $idx;
    }

    public function lookup(string $name): array|null
    {
        if (isset($this->locals[$name])) {
            return ['scope' => 'local'] + $this->locals[$name];
        }
        if (isset($this->globals[$name])) {
            return ['scope' => 'global'] + $this->globals[$name];
        }
        return null;
    }
}

final class CodeGen
{
    private array $consts = [];
    /** @var array<string,int> */
    private array $constIndex = [];
    private SymbolTable $sym;
    /** @var array<string,FunctionBC> */
    private array $functions = [];

    public function __construct()
    {
        $this->sym = new SymbolTable();
        // 预留内置名称（作为全局标识符由 VM 处理，使用名字常量索引 CALL_NAME）
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
        // 一遍扫描：预先注册所有函数名为全局（递归扫描，兼容 include 产生的 BlockStmt）
        $this->walkStmts($prog->stmts, function ($s) {
            if ($s instanceof FuncDecl) {
                $this->sym->setGlobalMut($s->name, true);
            }
        });
        // 第二遍：生成所有函数体字节码（递归）
        $this->walkStmts($prog->stmts, function ($s) {
            if ($s instanceof FuncDecl) {
                $this->emitFunction($s);
            }
        });
        // 第三遍：其它顶层语句（全局变量/常量/执行逻辑）
        foreach ($prog->stmts as $s) {
            if (!($s instanceof FuncDecl)) {
                $this->emitStmt($code, $s, null);
            }
        }
        $code[] = Op::HALT;
        return $code;
    }

    /**
     * 递归遍历语句树，应用回调
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
            // FuncDecl 的 body 不需要在预注册阶段继续深入，但深入也无害，这里保持不深入以减少遍历
        }
    }

    private function emitFunction(FuncDecl $f): void
    {
        $prevSym = $this->sym;
        $this->sym = new SymbolTable();
        // 函数参数作为局部变量，从0开始分配
        foreach ($f->params as $p) {
            $this->sym->defineLocal($p, false);
        }
        $code = [];
        $this->emitBlock($code, $f->body, $this->sym);
        // 默认返回 null
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
        // 块作用域：局部层次我们简单实现为在 codegen 阶段仍映射到当前函数的 SymbolTable
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
            $code[] = -1; // patch later
            // 真路径：弹出条件值后执行 then
            $code[] = Op::POP;
            $this->emitStmt($code, $s->then, $fnScope);
            if ($s->else !== null) {
                $code[] = Op::JMP;
                $jEnd = count($code);
                $code[] = -1;
                // 假路径入口：修补并弹出条件值
                $code[$jFalse] = count($code);
                $code[] = Op::POP;
                $this->emitStmt($code, $s->else, $fnScope);
                $code[$jEnd] = count($code);
            } else {
                // 无 else：假路径仅需修补并弹出条件
                $code[$jFalse] = count($code);
                $code[] = Op::POP;
            }
            return;
        }
        if ($s instanceof WhileStmt) {
            $start = count($code);
            $this->emitExpr($code, $s->cond, $fnScope);
            $code[] = Op::JMP_IF_FALSE;
            $jExit = count($code);
            $code[] = -1;
            // 条件为真：弹出条件值，执行循环体
            $code[] = Op::POP;
            $this->emitStmt($code, $s->body, $fnScope);
            $code[] = Op::JMP;
            $code[] = $start;
            // 条件为假：修补并弹出条件值
            $code[$jExit] = count($code);
            $code[] = Op::POP;
            return;
        }
        if ($s instanceof ForStmt) {
            // for (init; cond; step) body
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
            // 条件为真：弹出条件值，执行循环体
            $code[] = Op::POP;
            $this->emitStmt($code, $s->body, $fnScope);
            if ($s->step) {
                $this->emitExpr($code, $s->step, $fnScope);
                $code[] = Op::POP;
            }
            $code[] = Op::JMP;
            $code[] = $start;
            // 条件为假：修补并弹出条件值
            $code[$jExit] = count($code);
            $code[] = Op::POP;
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
                // 可能是内置函数名，在 CALL_NAME 时才解析
                // 将名字作为常量放栈上（供 CALL_NAME 使用）
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
                // 短路语义，确保栈顶仅保留结果
                // AND: left && right => if (!left) { result=left } else { pop(left); result=right }
                // OR:  left || right => if (left) { result=left } else { pop(left); result=right }
                $this->emitExpr($code, $e->left, $fnScope);
                if ($e->op === '&&') {
                    $code[] = Op::JMP_IF_FALSE;
                    $jFalse = count($code);
                    $code[] = -1; // 如果为假，跳到结尾，保留左值
                    // 为真路径：丢弃左值，计算右值
                    $code[] = Op::POP;
                    $this->emitExpr($code, $e->right, $fnScope);
                    // 结尾位置
                    $code[$jFalse] = count($code);
                } else { // '||'
                    // 如果为假，去计算右值；如果为真，直接跳过右值并保留左值
                    $code[] = Op::JMP_IF_FALSE;
                    $jFalse = count($code);
                    $code[] = -1;
                    $code[] = Op::JMP;
                    $jEnd = count($code);
                    $code[] = -1; // left 为真，跳转到结尾，保留左值
                    // 为假路径：丢弃左值，计算右值
                    $code[$jFalse] = count($code);
                    $code[] = Op::POP;
                    $this->emitExpr($code, $e->right, $fnScope);
                    // 结尾
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
            // 左值支持：标识符 或 索引 arr[index]（仅当 arr 是变量名）
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
                // 仅支持变量名的数组索引赋值：arr[index] = value
                if (!($e->left->array instanceof Ident)) {
                    // todo
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
                // 生成：load arr; index; value; ARRAY_SET => [arr, value]
                $this->emitExpr($code, $e->left->array, $fnScope);
                $this->emitExpr($code, $e->left->index, $fnScope);
                $this->emitExpr($code, $e->right, $fnScope);
                $code[] = Op::ARRAY_SET; // push: updatedArr, value
                // 将 updatedArr 存回变量，保持表达式值在栈顶（value）
                if ($sym['scope'] === 'local') {
                    $code[] = Op::STORE_LOCAL;
                    $code[] = $sym['index'];
                } else {
                    $code[] = Op::STORE_GLOBAL;
                    $code[] = $sym['index'];
                }
                // STORE_* 不弹栈，仍保留 updatedArr 在栈顶；我们需要将其弹出，只保留赋值值
                $code[] = Op::POP; // 弹出 updatedArr
                return;
            }
            throw new CGError('invalid assignment lvalue');
        }
        if ($e instanceof CallExpr) {
            // 简化：仅支持调用名字 callee 为 Ident 或 运行期名字（上面 Ident 未解析成符号时已放名字常量）
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
            // callee 任意表达式：我们先计算出其名字字符串
            $this->emitExpr($code, $e->callee, $fnScope);
            $nameTempConst = $this->internConst('__call_dynamic'); // 标识为动态，这里仍用 CALL_NAME，VM 读取栈顶为名字
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
        if (is_array($v)) {
            return 'arr:' . md5(json_encode($v));
        }
        if ($v === null) {
            return 'null';
        }
        if ($v === true) {
            return 'true';
        }
        if ($v === false) {
            return 'false';
        }
        return gettype($v) . ':' . (string)$v;
    }

    private function parseNumber(string $raw): int|float
    {
        return str_contains($raw, '.') ? (float)$raw : (int)$raw;
    }
}
