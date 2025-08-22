## 原生 PHP 实现的小型编译器

用原生`PHP`实现的一个小型编译器，无第三方依赖，包含词法分析、语法分析、字节码生成、虚拟机执行四个部分。

### 支持特性

- 变量(`let`)
- 常量(`const`)
- 函数(`func`)
- 判断语句(`if/else`)
- 循环语句(`while/for`)
- 返回语句(`return`)
- 引入语句(`include`)
- 块语句(`{}`)
- 表达式语句
- 数字、字符串、布尔值、`null`、数组类型
- 拥有作用域与生命周期
- 内置输出函数 `print(expr, ...)`（可变参数，每个参数独立输出并换行）

### 语言规范

#### 关键字

`let`、`const`、`func`、`return`、`if`、`else`、`while`、`for`、`true`、`false`、`null`

#### 语句

- `let`变量声明语句：`let x = 1;` 或 `let x;`
- `const`常量语句：`const PI = 3.14;`
- `func`函数声明语句：`func xxx() {}`
- `if`判断语句：`if (cond) stmt else stmt`
- `while`、`for`循环语句：`while (cond) stmt`；`for (init; cond; step) stmt`
- `return`返回语句：`return expr;`
- `include`语句：`include "path";`
- `{}`块语句：`{ ... }` 形成新作用域
- 表达式语句：`expr;`

#### 表达式

- 赋值表达式：`x = expr;`、`arr[i] = expr;`
- 二元表达式：`+ - * / % == != < <= > >= && ||`
- 一元表达式：`- !`
- 字面量：数字、字符串、布尔值、`null`
- 变量
- 数组字面量：`[a, b]`
- 索引：`arr[i]`
- 分组：`(expr)`
- 调用：`f(a, b)`

#### 字节码与 VM

- 栈式虚拟机，函数调用栈
- 指令（初版）：
    - 常量与变量：`CONST k`、`LOAD_GLOBAL i`、`STORE_GLOBAL i`、`LOAD_LOCAL i`、`STORE_LOCAL i`、`POP`
    - 算术与比较：`ADD SUB MUL DIV MOD NEG`、`EQ NE LT LE GT GE`
    - 逻辑：`NOT`、短路通过跳转实现 `AND OR`
    - 控制流：`JMP addr`、`JMP_IF_FALSE addr`
    - 调用：`CALL funcIndex argc`、`RET`、`HALT`
    - 数组：`ARRAY_NEW n`、`ARRAY_GET`、`ARRAY_SET`
    - 内置：`PRINT`（对栈顶进行输出并换行）
- 全局表：按名称分配索引；局部表：按函数内分配槽位

### 要求

`PHP 8.3`或`PHP 8.4`。

### 安装使用

```bash
$ composer require lwlwilliam/tiny-compiler:dev-main
$ cp -r vendor/lwlwilliam/tiny-compiler/examples # 注意：examples 后没有跟“/”
$ php examples/run.php
```