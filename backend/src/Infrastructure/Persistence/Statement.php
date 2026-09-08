<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\Exceptions\DomainErrorType;
use App\Domain\Exceptions\DomainException;

/** Todo SQL da aplicação passa por aqui: é o lugar de tipar o bind e de traduzir erro do banco. */
final class Statement
{
    private const string UNIQUE_VIOLATION = '23505';

    /** @param array<string, string|int|bool|null> $params */
    public static function execute(\PDO $pdo, string $sql, array $params): \PDOStatement
    {
        $statement = $pdo->prepare($sql);

        foreach ($params as $name => $value) {
            $statement->bindValue($name, $value, match (true) {
                is_int($value) => \PDO::PARAM_INT,
                is_bool($value) => \PDO::PARAM_BOOL,
                $value === null => \PDO::PARAM_NULL,
                default => \PDO::PARAM_STR,
            });
        }

        try {
            $statement->execute();
        } catch (\PDOException $exception) {
            throw self::translate($exception);
        }

        return $statement;
    }

    /**
     * UNIQUE violado é corrida entre duas requisições, não defeito: vira 409 em vez de 500.
     * A mensagem não cita a constraint porque isso exporia o esquema para quem chamou.
     */
    private static function translate(\PDOException $exception): \Throwable
    {
        return $exception->getCode() === self::UNIQUE_VIOLATION
            ? new DomainException('Resource already exists.', DomainErrorType::Conflict)
            : $exception;
    }
}
