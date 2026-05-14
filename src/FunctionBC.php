<?php
declare(strict_types=1);

namespace TinyCompiler;

final class FunctionBC
{
    /** @param int[] $code */
    public function __construct(public array $code, public int $nLocals)
    {
    }

    /** @return array{code: int[], nLocals: int} */
    public function toArray(): array
    {
        return ['code' => $this->code, 'nLocals' => $this->nLocals];
    }

    /** @param array{code: int[], nLocals: int} $data */
    public static function fromArray(array $data): self
    {
        return new self($data['code'], $data['nLocals']);
    }
}
