<?php
declare(strict_types=1);

namespace TinyCompiler;

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

    public function saveToFile(string $path): void
    {
        $data = $this->toArray();
        $content = '<?php return ' . var_export($data, true) . ';';
        file_put_contents($path, $content);
    }

    public static function loadFromFile(string $path): self
    {
        /** @var array $data */
        $data = require $path;
        return self::fromArray($data);
    }
}
