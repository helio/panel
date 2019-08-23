<?php

namespace Helio\Panel\Request;

class Log
{
    private const DEFAULT_SORT = 'desc';
    private const DEFAULT_CURSOR = null;

    /**
     * @var int
     */
    private $from = 0;

    /**
     * @var int
     */
    private $size = 10;

    /**
     * @var string|null
     */
    private $cursor = self::DEFAULT_CURSOR;

    /**
     * @var string
     */
    private $sort = self::DEFAULT_SORT;

    public function __construct(int $from, int $size, string $sort, string $cursor = self::DEFAULT_CURSOR)
    {
        $this->from = $from;
        $this->size = $size;
        $this->cursor = $cursor;
        $this->sort = $sort;
    }

    /**
     * Creates a new Log from request params.
     *
     * @param  array $params
     * @return Log
     */
    public static function fromParams(array $params): Log
    {
        $from = $params['from'] ?? 0;
        $size = $params['size'] ?? 10;
        $cursor = $params['cursor'] ?? self::DEFAULT_CURSOR;
        $sort = $params['sort'] ?? self::DEFAULT_SORT;

        return new self($from, $size, $sort, $cursor);
    }

    /**
     * @return int
     */
    public function getFrom(): int
    {
        return $this->from;
    }

    /**
     * @return int
     */
    public function getSize(): int
    {
        return $this->size;
    }

    /**
     * @return string|null
     */
    public function getCursor(): ?string
    {
        return $this->cursor;
    }

    /**
     * @return string
     */
    public function getSort(): string
    {
        return $this->sort;
    }
}
