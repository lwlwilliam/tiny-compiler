<?php
declare(strict_types=1);

namespace TinyCompiler;

require_once __DIR__ . '/Op.php';

final class VMError extends \Exception
{
}

final class Frame
{
    /**
     * @param int[] $code
     * @param int $ip
     * @param array<int,mixed> $locals
     */
    public function __construct(
        public array $code,
        public int   $ip,
        public array $locals,
    )
    {
    }
}

final class VM
{
    /** @var array<int,mixed> */
    private array $consts;
    /** @var array<string,int> */
    private array $globalsIndex;
    /** @var array<int,mixed> */
    private array $globals;
    /** @var array<string,FunctionBC> */
    private array $functions;

    /** @var array<mixed> */
    private array $stack = [];

    /** @var Frame[] */
    private array $callStack = [];

    public function __construct(array $consts, array $globalsIndex, array $functions)
    {
        $this->consts = $consts;
        $this->globalsIndex = $globalsIndex; // name=>idx
        $this->globals = array_fill(0, count($globalsIndex), null);
        $this->functions = $functions;
    }

    /**
     * @param int[] $entry
     * @throws VMError
     */
    public function run(array $entry): void
    {
        $frame = new Frame($entry, 0, []);
        $this->callStack[] = $frame;
        $this->exec();
    }

    /**
     * @throws VMError
     */
    private function exec(): void
    {
        while (!empty($this->callStack)) {
            $f = $this->currentFrame();
            if ($f->ip >= count($f->code)) {
                throw new VMError('IP out of range');
            }
            $op = $f->code[$f->ip++];
            switch ($op) {
                case Op::CONST_:
                    $k = $f->code[$f->ip++];
                    $this->stack[] = $this->consts[$k];
                    break;
                case Op::LOAD_GLOBAL:
                    $i = $f->code[$f->ip++];
                    $this->stack[] = $this->globals[$i] ?? null;
                    break;
                case Op::STORE_GLOBAL:
                    $i = $f->code[$f->ip++];
                    $val = end($this->stack);
                    $this->globals[$i] = $val;
                    break;
                case Op::LOAD_LOCAL:
                    $i = $f->code[$f->ip++];
                    $this->stack[] = $f->locals[$i] ?? null;
                    break;
                case Op::STORE_LOCAL:
                    $i = $f->code[$f->ip++];
                    $val = end($this->stack);
                    $f->locals[$i] = $val;
                    break;
                case Op::POP:
                    array_pop($this->stack);
                    break;

                case Op::ADD:
                    $this->bin(function ($a, $b) {
                        return $a + $b;
                    });
                    break;
                case Op::SUB:
                    $this->bin(function ($a, $b) {
                        return $a - $b;
                    });
                    break;
                case Op::MUL:
                    $this->bin(function ($a, $b) {
                        return $a * $b;
                    });
                    break;
                case Op::DIV:
                    $this->bin(function ($a, $b) {
                        return $a / $b;
                    });
                    break;
                case Op::MOD:
                    $this->bin(function ($a, $b) {
                        return $a % $b;
                    });
                    break;
                case Op::NEG:
                    $a = array_pop($this->stack);
                    $this->stack[] = -$a;
                    break;

                case Op::EQ:
                    $this->cmp(function ($a, $b) {
                        return $a == $b;
                    });
                    break;
                case Op::NE:
                    $this->cmp(function ($a, $b) {
                        return $a != $b;
                    });
                    break;
                case Op::LT:
                    $this->cmp(function ($a, $b) {
                        return $a < $b;
                    });
                    break;
                case Op::LE:
                    $this->cmp(function ($a, $b) {
                        return $a <= $b;
                    });
                    break;
                case Op::GT:
                    $this->cmp(function ($a, $b) {
                        return $a > $b;
                    });
                    break;
                case Op::GE:
                    $this->cmp(function ($a, $b) {
                        return $a >= $b;
                    });
                    break;
                case Op::NOT:
                    $a = array_pop($this->stack);
                    $this->stack[] = !$a;
                    break;
                case Op::JMP:
                    $addr = $f->code[$f->ip++];
                    $f->ip = $addr;
                    break;
                case Op::JMP_IF_FALSE:
                    $addr = $f->code[$f->ip++];
                    $cond = end($this->stack);
                    if (!$cond) {
                        $f->ip = $addr;
                    }
                    break;
                case Op::CALL_NAME:
                    $nameK = $f->code[$f->ip++];
                    $argc = $f->code[$f->ip++];
                    $fname = $this->consts[$nameK];
                    // 支持两类：内置函数（print），用户函数（functions 表中）
                    if ($fname === '__call_dynamic') {
                        // 动态调用：栈顶视为函数名字符串
                        $fname = array_splice($this->stack, count($this->stack) - $argc - 1, 1)[0];
                    }
                    if ($fname === 'print') {
                        // 可变参数：逐个弹出后按顺序输出
                        $args = $this->popArgs($argc);
                        foreach ($args as $arg) {
                            $this->doPrint($arg);
                        }
                        // print 返回 null
                        $this->stack[] = null;
                        break;
                    }
                    if (!isset($this->functions[$fname])) {
                        throw new VMError('undefined function: ' . $fname);
                    }
                    $fn = $this->functions[$fname];
                    $args = $this->popArgs($argc);
                    $locals = array_fill(0, max($fn->nLocals, count($args)), null);
                    // 将参数放入局部槽位
                    for ($i = 0; $i < count($args); $i++) {
                        $locals[$i] = $args[$i];
                    }
                    // 压入新帧
                    $this->callStack[count($this->callStack) - 1] = $f; // 更新当前帧引用
                    $this->callStack[] = new Frame($fn->code, 0, $locals);
                    break;
                case Op::RET:
                    $ret = array_pop($this->stack);
                    array_pop($this->callStack); // 弹出当前帧
                    if (empty($this->callStack)) { // 从 entry 返回
                        $this->stack[] = $ret; // 可忽略
                        return;
                    }
                    // 返回到调用者帧，压回返回值
                    $caller = $this->currentFrame();
                    $this->stack[] = $ret;
                    break;
                case Op::ARRAY_NEW:
                    $n = $f->code[$f->ip++];
                    $arr = array_splice($this->stack, count($this->stack) - $n, $n);
                    $this->stack[] = $arr;
                    break;
                case Op::ARRAY_GET:
                    $idx = array_pop($this->stack);
                    $arr = array_pop($this->stack);
                    if (!is_array($arr)) {
                        throw new VMError('index operation object is not an array');
                    }
                    $this->stack[] = $arr[(int)$idx] ?? null;
                    break;
                case Op::ARRAY_SET:
                    $val = array_pop($this->stack);
                    $idx = array_pop($this->stack);
                    $arr = array_pop($this->stack);
                    if (!is_array($arr)) {
                        throw new VMError('index operation object is not an array');
                    }
                    $arr[(int)$idx] = $val;
                    // 压回顺序：先值后更新后的数组 => [value, updatedArr]
                    $this->stack[] = $val;
                    $this->stack[] = $arr;
                    break;
                case Op::PRINT:
                    $v = array_pop($this->stack);
                    $this->doPrint($v);
                    $this->stack[] = null;
                    break;
                case Op::HALT:
                    return;
                default:
                    throw new VMError('unknown instruction: ' . $op);
            }
        }
    }

    private function currentFrame(): Frame
    {
        return $this->callStack[count($this->callStack) - 1];
    }

    /** @return array<int,mixed> args */
    private function popArgs(int $argc): array
    {
        $args = [];
        for ($i = 0; $i < $argc; $i++) {
            array_unshift($args, array_pop($this->stack));
        }
        return $args;
    }

    // 执行二元算术/逻辑运算：弹出右、左操作数并压入结果
    private function bin(callable $op): void
    {
        $b = array_pop($this->stack);
        $a = array_pop($this->stack);
        $this->stack[] = $op($a, $b);
    }

    // 执行比较运算并压入布尔结果
    private function cmp(callable $op): void
    {
        $b = array_pop($this->stack);
        $a = array_pop($this->stack);
        $this->stack[] = (bool)$op($a, $b);
    }

    private function doPrint(mixed $v): void
    {
        if (is_array($v)) {
            echo json_encode($v, JSON_UNESCAPED_UNICODE);
        } else {
            if (is_bool($v)) {
                echo ($v ? 'true' : 'false');
            } elseif ($v === null) {
                echo "null";
            } else {
                echo $v;
            }
        }
    }
}
