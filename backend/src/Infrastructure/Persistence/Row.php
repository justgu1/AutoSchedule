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

    public function nullableInt(string $column): ?int
    {
        return ($this->values[$column] ?? null) === null ? null : $this->int($column);
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
     * `timestamptz` volta com o fuso embutido no próprio objeto -- comparar `format()` dele com um
     * horário montado localmente compara string de fusos diferentes pro mesmo instante, calado.
     */
    public function localDateTime(string $column): \DateTimeImmutable
    {
        return $this->dateTime($column)->setTimezone(new \DateTimeZone(date_default_timezone_get()));
    }

    public function nullableLocalDateTime(string $column): ?\DateTimeImmutable
    {
        return ($this->values[$column] ?? null) === null ? null : $this->localDateTime($column);
    }

    /** `!` zera a data pro epoch: só a hora importa, e duas colunas `time` viram comparáveis entre si. */
    public function time(string $column): \DateTimeImmutable
    {
        $value = \DateTimeImmutable::createFromFormat('!H:i:s', $this->string($column));

        return $value instanceof \DateTimeImmutable ? $value : throw $this->unexpected($column, 'time');
    }

    public function nullableTime(string $column): ?\DateTimeImmutable
    {
        return ($this->values[$column] ?? null) === null ? null : $this->time($column);
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
     * @return ?T
     */
    public function nullableEnum(string $enum, string $column): ?\BackedEnum
    {
        $value = $this->nullableString($column);

        return $value === null ? null : $enum::from($value);
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
