<?php
declare(strict_types=1);

namespace TinyCompiler;

final class Frame
{
    /**
     * @param int[] $code
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
