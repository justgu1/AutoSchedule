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

        $this->assertSame(['name' => 'Ada', 'email' => 'ada@example.com'], $validated->all());
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

    /** Sigla fora das 27 tem que virar 422 aqui -- se passar, `Uf::from()` lança ValueError e o cliente recebe 500. */
    #[Test]
    public function rejeita_sigla_de_estado_que_nao_existe(): void
    {
        try {
            Validator::validate(['state' => 'XX'], ['state' => 'required|uf']);
            $this->fail('Expected a DomainException to be thrown.');
        } catch (DomainException $exception) {
            $this->assertSame(['state' => 'The state field must be a valid Brazilian state code.'], $exception->errors());
        }
    }

    #[Test]
    public function rejeita_valor_abaixo_do_minimo(): void
    {
        try {
            Validator::validate(['password' => 'abc'], ['password' => 'min:8']);
            $this->fail('Expected a DomainException to be thrown.');
        } catch (DomainException $exception) {
            $this->assertSame(['password' => 'The password field must be at least 8.'], $exception->errors());
        }
    }

    #[Test]
    public function aceita_valor_dentro_do_min_e_max(): void
    {
        $validated = Validator::validate(['password' => 'a-long-enough-password'], ['password' => 'min:8|max:64']);

        $this->assertSame(['password' => 'a-long-enough-password'], $validated->all());
    }

    #[Test]
    public function rejeita_valor_que_nao_e_numero(): void
    {
        try {
            Validator::validate(['price' => 'grátis'], ['price' => 'numeric']);
            $this->fail('Expected a DomainException to be thrown.');
        } catch (DomainException $exception) {
            $this->assertSame(['price' => 'The price field must be a number.'], $exception->errors());
        }
    }

    /** `between` lê o valor e `max` lê o tamanho: um ano de 4 dígitos passaria em `max:2100` sem checar nada. */
    #[Test]
    public function rejeita_valor_fora_da_faixa_do_between(): void
    {
        try {
            Validator::validate(['year' => 1800], ['year' => 'between:1900,2100']);
            $this->fail('Expected a DomainException to be thrown.');
        } catch (DomainException $exception) {
            $this->assertSame(['year' => 'The year field must be between 1900 and 2100.'], $exception->errors());
        }
    }

    #[Test]
    public function aceita_numero_como_string_ou_como_numero(): void
    {
        $rules = ['year' => 'numeric|between:1900,2100', 'price' => 'numeric'];

        $this->assertSame(['year' => 2023, 'price' => 89900.5], Validator::validate(['year' => 2023, 'price' => 89900.5], $rules)->all());
        $this->assertSame(['year' => '2023', 'price' => '89900.50'], Validator::validate(['year' => '2023', 'price' => '89900.50'], $rules)->all());
    }

    #[Test]
    public function aceita_valor_presente_na_lista_do_in(): void
    {
        $validated = Validator::validate(['role' => 'admin'], ['role' => 'required|in:admin,seller,customer']);

        $this->assertSame(['role' => 'admin'], $validated->all());
    }

    #[Test]
    public function rejeita_valor_fora_da_lista_do_in(): void
    {
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

        $this->assertSame([], $validated->all());
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
