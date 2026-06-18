<?php

declare(strict_types=1);

namespace Sendara;

/**
 * @template T
 */
final class MessagePage
{
    /**
     * @param array<int, array<string, mixed>> $messages
     */
    public function __construct(
        public readonly array $messages,
        public readonly ?string $nextCursor,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $messages = is_array($data['messages'] ?? null) ? $data['messages'] : [];
        $cursor = $data['next_cursor'] ?? null;

        return new self(
            array_values($messages),
            is_string($cursor) && $cursor !== '' ? $cursor : null,
        );
    }

    public function hasMore(): bool
    {
        return $this->nextCursor !== null;
    }
}
