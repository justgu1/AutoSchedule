<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Validation;

use App\Domain\Exceptions\DomainErrorType;
use App\Domain\Exceptions\DomainException;
use App\Infrastructure\Validation\Validator;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ValidatorTest extends TestCase
{
    #[Test]
    public function retorna_apenas_os_campos_declarados_nas_regras(): void
    {
        $validated = Validator::validate(
            ['name' => 'Ada', 'email' => 'ada@example.com', 'ignored' => 'x'],
            ['name' => 'required', 'email' => 'required|email'],
        );

        $this->assertSame(['name' => 'Ada', 'email' => 'ada@example.com'], $validated);
    }

    #[Test]
    public function lanca_domain_exception_de_validacao_quando_campo_obrigatorio_falta(): void
    {
        try {
            Validator::validate([], ['name' => 'required']);
            $this->fail('Expected a DomainException to be thrown.');
        } catch (DomainException $exception) {
            $this->assertSame(DomainErrorType::Validation, $exception->type());
            $this->assertSame(['name' => 'The name field is required.'], $exception->errors());
        }
    }

    #[Test]
    public function rejeita_email_invalido(): void
    {
        try {
            Validator::validate(['email' => 'not-an-email'], ['email' => 'required|email']);
            $this->fail('Expected a DomainException to be thrown.');
        } catch (DomainException $exception) {
            $this->assertSame(['email' => 'The email field must be a valid email address.'], $exception->errors());
        }
    }

    #[Test]
    public function rejeita_uuid_invalido(): void
    {
        try {
            Validator::validate(['id' => 'not-a-uuid'], ['id' => 'uuid']);
            $this->fail('Expected a DomainException to be thrown.');
        } catch (DomainException $exception) {
            $this->assertSame(['id' => 'The id field must be a valid UUID.'], $exception->errors());
        }
    }

    #[Test]
    public function valida_min_e_max_pelo_tamanho_da_string(): void
    {
        try {
            Validator::validate(['password' => 'abc'], ['password' => 'min:8']);
            $this->fail('Expected a DomainException to be thrown.');
        } catch (DomainException $exception) {
            $this->assertSame(['password' => 'The password field must be at least 8.'], $exception->errors());
        }

        $validated = Validator::validate(['password' => 'a-long-enough-password'], ['password' => 'min:8|max:64']);
        $this->assertSame(['password' => 'a-long-enough-password'], $validated);
    }

    #[Test]
    public function valida_in_contra_uma_lista_de_valores(): void
    {
        $validated = Validator::validate(['role' => 'admin'], ['role' => 'required|in:admin,seller,customer']);
        $this->assertSame(['role' => 'admin'], $validated);

        try {
            Validator::validate(['role' => 'superuser'], ['role' => 'in:admin,seller,customer']);
            $this->fail('Expected a DomainException to be thrown.');
        } catch (DomainException $exception) {
            $this->assertSame(['role' => 'The role field must be one of: admin,seller,customer.'], $exception->errors());
        }
    }

    #[Test]
    public function ignora_regras_diferentes_de_required_quando_o_campo_esta_ausente(): void
    {
        $validated = Validator::validate([], ['nickname' => 'email|min:3']);

        $this->assertSame([], $validated);
    }

    #[Test]
    public function acumula_um_erro_por_campo_e_para_na_primeira_regra_que_falhar(): void
    {
        try {
            Validator::validate(['email' => '', 'role' => 'root'], [
                'email' => 'required|email',
                'role' => 'in:admin,seller,customer',
            ]);
            $this->fail('Expected a DomainException to be thrown.');
        } catch (DomainException $exception) {
            $this->assertSame([
                'email' => 'The email field is required.',
                'role' => 'The role field must be one of: admin,seller,customer.',
            ], $exception->errors());
        }
    }
}
