<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

/** O `mixed` que sai do PDO morre aqui, em vez de virar cast solto espalhado por nove repositórios. */
final readonly class Row
{
    /** @param array<array-key, mixed> $values */
    private function __construct(private array $values)
    {
    }

    /** Recebe `mixed` porque é isso que `PDOStatement::fetch()` promete, e é aqui que essa promessa é conferida. */
    public static function from(mixed $row): self
    {
        return is_array($row) ? new self($row) : throw new \UnexpectedValueException('Expected a database row.');
    }

    public function string(string $column): string
    {
        $value = $this->values[$column] ?? null;

        return is_string($value) ? $value : throw $this->unexpected($column, 'string');
    }

    public function nullableString(string $column): ?string
    {
        return ($this->values[$column] ?? null) === null ? null : $this->string($column);
    }

    public function int(string $column): int
    {
        $value = $this->values[$column] ?? null;

        return is_int($value) || is_numeric($value) ? (int) $value : throw $this->unexpected($column, 'int');
    }

    public function bool(string $column): bool
    {
        $value = $this->values[$column] ?? null;

        return is_bool($value) ? $value : throw $this->unexpected($column, 'bool');
    }

    public function dateTime(string $column): \DateTimeImmutable
    {
        return new \DateTimeImmutable($this->string($column));
    }

    public function nullableDateTime(string $column): ?\DateTimeImmutable
    {
        return ($this->values[$column] ?? null) === null ? null : $this->dateTime($column);
    }

    /**
     * @template T of \BackedEnum
     *
     * @param class-string<T> $enum
     *
     * @return T
     */
    public function enum(string $enum, string $column): \BackedEnum
    {
        return $enum::from($this->string($column));
    }

    /**
     * @template T of \BackedEnum
     *
     * @param class-string<T> $enum
     *
     * @return list<T>
     */
    public function enumList(string $enum, string $column): array
    {
        /** @var list<T> $cases */
        $cases = array_map($enum::from(...), $this->stringList($column));

        return $cases;
    }

    /** @return list<string> */
    public function stringList(string $column): array
    {
        return PostgresArray::fromText($this->nullableString($column));
    }

    private function unexpected(string $column, string $expected): \UnexpectedValueException
    {
        return new \UnexpectedValueException(sprintf('Column "%s" is not a %s.', $column, $expected));
    }
}
