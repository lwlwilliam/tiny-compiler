<?php
declare(strict_types=1);

namespace TinyCompiler;

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
