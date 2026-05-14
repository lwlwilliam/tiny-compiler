## 原生 PHP 实现的小型编译器

用原生 PHP 实现的一个小型编译器，无第三方依赖，包含词法分析、语法分析、字节码生成、虚拟机执行四个阶段。支持将源码编译为 `.bc` 字节码文件，之后可脱离编译器单独运行。

### 编译器流水线

```
源码 (.l)
  → Lexer (词法分析) → Token 流
  → Parser (语法分析) → AST
  → CodeGen (代码生成) → ModuleBC (字节码模块)
  → VM (虚拟机执行)

        ↓ compile.php

      .bc 字节码文件（可单独分发、运行）
```

### 要求

`PHP 8.3` 或 `PHP 8.4`。

### 安装

```bash
$ composer require lwlwilliam/tiny-compiler:dev-main
```

### 运行源码

```bash
$ php examples/run.php examples/codes/demo.l
```

输出：

```
y = 44; z = 9; foo = 10
arr = [1,42,3]
add(3, 4) = 7
fact(10) = 55
twice(8) = 16
GREETING = Hello
y > 20
sum = 10
Hello world number: 1
Hello world number: 2
Hello world number: 4
Hello world number: 6
Hello world number: 8
j == 666
true && 123 = 123
false && 123 = false
true || 456 = true
false || 456 = 456
PI = 3.14
null = null
10 % 3 = 1
flag is false
first element: 1
nested: 10
tabs:	between
say "hello"
inside block: 999
```

### 编译为字节码 & 单独运行

将源码编译成二进制 `.bc` 字节码文件：

```bash
$ php examples/compile.php examples/codes/demo.l
Compiled: examples/codes/demo.l -> examples/codes/demo.bc
  size: 3,056 bytes
  consts: 47
  globals: 16
  functions: 4
  entry ops: 462
```

生成的 `.bc` 文件是紧凑的二进制格式（`TBC\x00` 魔数头），无需 Lexer / Parser / CodeGen，仅需 VM 即可运行：

```bash
$ php examples/run.php examples/codes/demo.bc
# 输出与直接运行源码完全一致
```

`.bc` 文件可分发到任何安装了 `TinyCompiler` 的环境中直接执行。如需人类可读的字节码文件，可使用 `--php` 导出为 `var_export` PHP 格式：

```bash
$ php examples/compile.php --php examples/codes/demo.l
Compiled: examples/codes/demo.l -> examples/codes/demo.bc.php (PHP (human-readable))
  size: 10,088 bytes
```

导出的 `.bc.php` 文件结构一览：

```php
<?php return array (
  'version' => 1,
  'consts' =>
  array (
    0 => NULL,
    1 => 1,
    2 => 2,
    3 => 'fact2',
    4 => 'Hello',
    5 => 3.14,
    // ... 共 47 个常量
  ),
  'globals' =>
  array (
    'twice' => 0,
    'add' => 1,
    'fact' => 2,
    // ... 共 16 个
  ),
  'functions' =>
  array (
    'twice' =>
    array (
      'code' => array ( 1, 0, 4, 0, 4, 0, 10, 1, 1, 1, 51 ),
      'nLocals' => 1,
    ),
    // ... 共 4 个函数
  ),
  'entry' =>
  array (
    1, 0, 2, 1, 1, ...
    // 462 条字节码指令
  ),
);
```

---

## 语言规范

### 关键字

`let` `const` `func` `return` `if` `else` `while` `for` `break` `continue` `true` `false` `null` `include`

### 注释

```c
// 单行注释
/*
   多行注释
*/
```

---

## 语言特性演示

### 变量声明 (`let`)

```
let x = 10;
let y;           // 未初始化，默认 null
y = 2 + 2 * (x + 5 + 6);
print("y = ", y, "\n");  // y = 44
```

### 常量声明 (`const`)

```
const PI = 3.14;
print("PI = ", PI, "\n");  // PI = 3.14
```

### 函数 (`func`)

```
func add(a, b) {
    return a + b;
}

print("add(3, 4) = ", add(3, 4), "\n");  // add(3, 4) = 7
```

### 递归函数

```
func fib(n) {
    if (n < 2) {
        return n;
    }
    return fib(n - 1) + fib(n - 2);
}

print("fib(10) = ", fib(10), "\n");  // fib(10) = 55
```

### 条件语句 (`if` / `else`)

```
let score = 85;

if (score >= 90) {
    print("A\n");
} else if (score >= 80) {
    print("B\n");
} else {
    print("C\n");
}
```

### 循环 (`while`)

```
let n = 1;
let sum = 0;
while (n <= 100) {
    sum = sum + n;
    n = n + 1;
}
print("sum 1..100 = ", sum, "\n");  // sum 1..100 = 5050
```

### 循环 (`for`)

```
let total = 0;
for (let i = 0; i < 5; i = i + 1) {
    total = total + i;
}
print("total = ", total, "\n");  // total = 10 (0+1+2+3+4)

// for 的三个表达式都可以省略
let j = 0;
for (; j < 3;) {
    print("loop\n");
    j = j + 1;
}
```

### 循环控制 (`break` / `continue`)

```
let i = 0;
while (true) {
    i = i + 1;
    if (i == 3 || i == 5) {
        continue;          // 跳过 3 和 5
    }
    print("i = ", i, "\n");
    if (i == 7) {
        break;             // 到 7 停止
    }
}
// 输出: i = 1, i = 2, i = 4, i = 6, i = 7
```

### 返回值 (`return`)

```
func max(a, b) {
    if (a > b) {
        return a;
    }
    return b;
}

print("max(10, 20) = ", max(10, 20), "\n");  // max(10, 20) = 20
```

### 块语句 (`{}`)

`{}` 包裹多条语句，可出现在任何允许单条语句的位置：

```
if (true) {
    print("first\n");
    print("second\n");
}
```

### 引入其他文件 (`include`)

```
include "lib.l";

print("GREETING = ", GREETING, "\n");   // 使用 lib.l 中定义的常量
print("twice(8) = ", twice(8), "\n");   // 使用 lib.l 中定义的函数
```

### 数组

```
let arr = [1, 2, 3];
arr[1] = 42;
print("arr = ", arr, "\n");             // arr = [1,42,3]
print("arr[0] = ", arr[0], "\n");      // arr[0] = 1
```

### 逻辑短路 (`&&` / `||`)

```
print("true  && 123 = ", true  && 123, "\n");   // true  && 123 = 123
print("false && 123 = ", false && 123, "\n");   // false && 123 = false
print("true  || 456 = ", true  || 456, "\n");   // true  || 456 = true
print("false || 456 = ", false || 456, "\n");   // false || 456 = 456
```

> `&&` / `||` 有短路语义，返回值是最后一个被求值的表达式的值（不是布尔值）。

### 字面量

| 类型 | 示例 | 说明 |
|------|------|------|
| 整数 | `42`, `0`, `-7` | |
| 浮点数 | `3.14`, `-0.5` | |
| 字符串 | `"hello"`, `"a\nb"` | 支持 `\n` `\r` `\t` `\"` `\'` `\\` |
| 布尔值 | `true`, `false` | |
| 空值 | `null` | |
| 数组 | `[1, "hi", true]` | 任意类型混排 |

### 运算符

| 优先级 | 运算符 | 结合性 |
|--------|--------|--------|
| 最低 | `=` | 右结合 |
| | `\|\|` | 左结合 |
| | `&&` | 左结合 |
| | `==` `!=` | 左结合 |
| | `<` `<=` `>` `>=` | 左结合 |
| | `+` `-` | 左结合 |
| | `*` `/` `%` | 左结合 |
| | `-` (取负) `!` (逻辑非) | 右结合 |
| 最高 | `()` 调用 `[]` 索引 | 左结合 |

### 内置函数

仅提供一个内置输出函数 `print`，支持可变参数：

```
print("Hello", " ", "World", "\n");  // Hello World

print(1, 2, 3, "\n");                // 123
```

`print` 对不同类型值的输出格式：

| 值 | 输出 |
|----|------|
| `"abc"` | `abc` |
| `42` | `42` |
| `3.14` | `3.14` |
| `true` | `true` |
| `false` | `false` |
| `null` | `null` |
| `[1, 2, 3]` | `[1,2,3]` |
