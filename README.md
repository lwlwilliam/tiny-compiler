### 原生 PHP 实现的小型编译器

用原生`PHP`实现的一个小型编译器，无第三方依赖，包含词法分析、语法分析、字节码生成、虚拟机执行四个部分。

支持特性：
- 变量(`let`)
- 常量(`const`)
- 函数(`fun`)
- 判断语句(`if/else`)
- 循环语句(`while/for`)
- 返回语句(`return`)
- 数字、字符串、数组类型
- 拥有作用域与生命周期

语言规范：
- 关键字：`let`、`const`、`fun`、`return`、`if`、`else`、`while`、`for`、`true`、`false`、`null`
- 输出：内置函数 `print(expr, ...)`（可变参数，每个参数独立输出并换行）。
- 语句：
    - 变量声明：`let x = 1;` 或 `let x;`
    - 常量声明：`const PI = 3.14;`
    - 赋值：`x = expr;`、`arr[i] = expr;`
    - 块：`{ ... }` 形成新作用域
    - 条件：`if (cond) stmt else stmt`
    - 循环：`while (cond) stmt`；`for (init; cond; step) stmt`
    - 返回：`return expr;`
    - 表达式语句：`expr;`
- 表达式：字面量（数字、字符串、`true`、`false`、`null`）、变量、数组字面量 `[a, b]`、索引 `arr[i]`、
  一元 `- !`、二元 `+ - * / % == != < <= > >= && ||`、分组 `(expr)`、调用 `f(a,b)`。
- 作用域：词法作用域，块级；函数拥有独立局部表；不支持闭包捕获外部局部（可访问全局）。
- 常量不可重新赋值（编译期检查）。

字节码与`VM`：
- 栈式虚拟机，函数调用栈。
- 指令（初版）：
    - 常量与变量：`CONST k`、`LOAD_GLOBAL i`、`STORE_GLOBAL i`、`LOAD_LOCAL i`、`STORE_LOCAL i`、`POP`
    - 算术与比较：`ADD SUB MUL DIV MOD NEG`、`EQ NE LT LE GT GE`
    - 逻辑：`NOT`、短路通过跳转实现 `AND OR`
    - 控制流：`JMP addr`、`JMP_IF_FALSE addr`
    - 调用：`CALL funcIndex argc`、`RET`、`HALT`
    - 数组：`ARRAY_NEW n`、`ARRAY_GET`、`ARRAY_SET`
    - 内置：`PRINT`（对栈顶进行输出并换行）
- 全局表：按名称分配索引；局部表：按函数内分配槽位。

### 要求

`PHP 8.3`或`PHP 8.4`。

### 使用

```bash
$ composer require lwlwilliam/tiny-compiler:dev-main
$ cp -r vendor/lwlwilliam/tiny-compiler/examples # 注意：examples 后没有跟“/”
$ php examples/run.php
```