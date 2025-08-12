<?php
declare(strict_types=1);

namespace TinyCompiler;

final class Op {
    public const CONST_ = 1;          // 操作数: constIndex
    public const LOAD_GLOBAL = 2;      // 操作数: globalIndex
    public const STORE_GLOBAL = 3;     // 操作数: globalIndex
    public const LOAD_LOCAL = 4;       // 操作数: localIndex
    public const STORE_LOCAL = 5;      // 操作数: localIndex
    public const POP = 6;

    public const ADD = 10;
    public const SUB = 11;
    public const MUL = 12;
    public const DIV = 13;
    public const MOD = 14;
    public const NEG = 15;

    public const EQ = 20;
    public const NE = 21;
    public const LT = 22;
    public const LE = 23;
    public const GT = 24;
    public const GE = 25;

    public const NOT = 30;

    public const JMP = 40;             // 操作数: addr
    public const JMP_IF_FALSE = 41;    // 操作数: addr

    public const CALL_NAME = 50;       // 操作数: nameConstIndex, argc
    public const RET = 51;
    public const HALT = 52;

    public const ARRAY_NEW = 60;       // 操作数: count
    public const ARRAY_GET = 61;       // 无操作数(pop index, array)
    public const ARRAY_SET = 62;       // 无操作数(pop value, index, array; push value)

    public const PRINT = 70;           // 无操作数(pop value and print with newline)
}
