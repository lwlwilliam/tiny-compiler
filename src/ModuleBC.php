<?php
declare(strict_types=1);

namespace TinyCompiler;

final class ModuleBC
{
    private const MAGIC = "TBC\x00";
    private const VERSION = 1;

    private const TAG_NULL = 0;
    private const TAG_BOOL = 1;
    private const TAG_INT = 2;
    private const TAG_FLOAT = 3;
    private const TAG_STRING = 4;

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

    public function saveToFile(string $path): void
    {
        file_put_contents($path, $this->serialize());
    }

    public static function loadFromFile(string $path): self
    {
        $data = file_get_contents($path);
        if ($data === false) {
            throw new \RuntimeException("cannot read file: $path");
        }
        if (str_starts_with($data, self::MAGIC)) {
            return self::deserialize($data);
        }
        if (str_starts_with($data, '<?php')) {
            /** @var array $arr */
            $arr = require $path;
            return self::fromArray($arr);
        }
        throw new \RuntimeException("unknown bytecode format: $path");
    }

    /**
     * @return array{version: int, consts: array, globals: array, functions: array, entry: int[]}
     */
    public function toArray(): array
    {
        $funcs = [];
        foreach ($this->functions as $name => $fn) {
            $funcs[$name] = $fn->toArray();
        }
        return [
            'version' => 1,
            'consts' => $this->consts,
            'globals' => $this->globals,
            'functions' => $funcs,
            'entry' => $this->entry,
        ];
    }

    /**
     * @param array{version: int, consts: array, globals: array, functions: array, entry: int[]} $data
     */
    public static function fromArray(array $data): self
    {
        $funcs = [];
        foreach ($data['functions'] as $name => $fnData) {
            $funcs[$name] = FunctionBC::fromArray($fnData);
        }
        return new self($data['consts'], $data['globals'], $funcs, $data['entry']);
    }

    public function saveToPhpFile(string $path): void
    {
        $data = $this->toArray();
        $content = '<?php return ' . var_export($data, true) . ';';
        file_put_contents($path, $content);
    }

    public static function loadFromPhpFile(string $path): self
    {
        /** @var array $data */
        $data = require $path;
        return self::fromArray($data);
    }

    // ---- binary serialization ----

    private function serialize(): string
    {
        $buf = '';

        $buf .= self::MAGIC;
        $buf .= pack('V', self::VERSION);
        $buf .= pack('V', 0); // flags

        $buf .= pack('V', count($this->consts));
        $buf .= pack('V', count($this->globals));
        $buf .= pack('V', count($this->functions));
        $buf .= pack('V', count($this->entry));

        foreach ($this->consts as $v) {
            $buf .= $this->serializeConst($v);
        }

        foreach ($this->globals as $name => $idx) {
            $buf .= $this->serializeString($name);
            $buf .= pack('V', $idx);
        }

        foreach ($this->functions as $name => $fn) {
            $buf .= $this->serializeString($name);
            $buf .= pack('V', $fn->nLocals);
            $buf .= pack('V', count($fn->code));
            foreach ($fn->code as $op) {
                $buf .= pack('V', $op);
            }
        }

        foreach ($this->entry as $op) {
            $buf .= pack('V', $op);
        }

        return $buf;
    }

    private static function deserialize(string $buf): self
    {
        $off = 0;

        $magic = substr($buf, $off, 4); $off += 4;
        if ($magic !== self::MAGIC) {
            throw new \RuntimeException('invalid bytecode magic');
        }
        $version = unpack('V', substr($buf, $off, 4))[1]; $off += 4;
        if ($version !== self::VERSION) {
            throw new \RuntimeException("unsupported bytecode version: $version");
        }
        $off += 4;

        $nConsts   = unpack('V', substr($buf, $off, 4))[1]; $off += 4;
        $nGlobals  = unpack('V', substr($buf, $off, 4))[1]; $off += 4;
        $nFuncs    = unpack('V', substr($buf, $off, 4))[1]; $off += 4;
        $nEntry    = unpack('V', substr($buf, $off, 4))[1]; $off += 4;

        $consts = [];
        for ($i = 0; $i < $nConsts; $i++) {
            [$v, $adv] = self::deserializeConst($buf, $off);
            $consts[] = $v;
            $off = $adv;
        }

        $globals = [];
        for ($i = 0; $i < $nGlobals; $i++) {
            [$name, $adv] = self::deserializeString($buf, $off);
            $off = $adv;
            $globals[$name] = unpack('V', substr($buf, $off, 4))[1];
            $off += 4;
        }

        $functions = [];
        for ($i = 0; $i < $nFuncs; $i++) {
            [$fname, $adv] = self::deserializeString($buf, $off);
            $off = $adv;
            $nLocals = unpack('V', substr($buf, $off, 4))[1]; $off += 4;
            $codeLen = unpack('V', substr($buf, $off, 4))[1]; $off += 4;
            $code = [];
            for ($j = 0; $j < $codeLen; $j++) {
                $code[] = unpack('V', substr($buf, $off, 4))[1];
                $off += 4;
            }
            $functions[$fname] = new FunctionBC($code, $nLocals);
        }

        $entry = [];
        for ($i = 0; $i < $nEntry; $i++) {
            $entry[] = unpack('V', substr($buf, $off, 4))[1];
            $off += 4;
        }

        return new self($consts, $globals, $functions, $entry);
    }

    private function serializeConst(mixed $v): string
    {
        if ($v === null) {
            return pack('C', self::TAG_NULL);
        }
        if (is_bool($v)) {
            return pack('C', self::TAG_BOOL) . pack('C', $v ? 1 : 0);
        }
        if (is_int($v)) {
            return pack('C', self::TAG_INT) . pack('P', $v);
        }
        if (is_float($v)) {
            return pack('C', self::TAG_FLOAT) . pack('e', $v);
        }
        if (is_string($v)) {
            return pack('C', self::TAG_STRING) . $this->serializeString($v);
        }
        return pack('C', self::TAG_NULL);
    }

    private static function deserializeConst(string $buf, int $off): array
    {
        $tag = unpack('C', $buf[$off])[1];
        $off += 1;

        return match ($tag) {
            self::TAG_NULL => [null, $off],
            self::TAG_BOOL => [(bool)unpack('C', $buf[$off])[1], $off + 1],
            self::TAG_INT => [unpack('P', substr($buf, $off, 8))[1], $off + 8],
            self::TAG_FLOAT => [unpack('e', substr($buf, $off, 8))[1], $off + 8],
            self::TAG_STRING => self::deserializeString($buf, $off),
            default => throw new \RuntimeException("unknown const tag: $tag"),
        };
    }

    private function serializeString(string $s): string
    {
        return pack('V', strlen($s)) . $s;
    }

    private static function deserializeString(string $buf, int $off): array
    {
        $len = unpack('V', substr($buf, $off, 4))[1];
        $off += 4;
        $s = substr($buf, $off, $len);
        $off += $len;
        return [$s, $off];
    }
}
