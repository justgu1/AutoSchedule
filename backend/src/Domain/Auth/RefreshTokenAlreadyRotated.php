<?php

declare(strict_types=1);

namespace App\Domain\Auth;

/** Outra requisição rotacionou o mesmo token primeiro. O que isso significa pro cliente é decisão do caso de uso. */
final class RefreshTokenAlreadyRotated extends \RuntimeException
{
}
