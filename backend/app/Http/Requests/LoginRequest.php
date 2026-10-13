<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Validação e tentativa de autenticação.
 *
 * A autenticação vive no Form Request, e não no controller, porque falha de
 * credencial é um erro de validação do ponto de vista da API: devolve 422
 * com a mensagem sob o campo de e-mail, no mesmo formato que o formulário
 * já sabe exibir.
 */
final class LoginRequest extends FormRequest
{
    /** Tentativas permitidas antes do bloqueio temporário. */
    private const MAX_ATTEMPTS = 5;

    /** Duração do bloqueio, em segundos. */
    private const DECAY_SECONDS = 60;

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['email' => 'e-mail', 'password' => 'senha'];
    }

    /**
     * Autentica, respeitando o limite de tentativas.
     *
     * @throws ValidationException
     */
    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        if (! Auth::attempt($this->only('email', 'password'), $this->boolean('remember'))) {
            RateLimiter::hit($this->throttleKey(), self::DECAY_SECONDS);

            // Mensagem única para e-mail inexistente e senha errada. Mensagens
            // distintas transformariam o formulário num oráculo de quais
            // e-mails estão cadastrados.
            throw ValidationException::withMessages([
                'email' => 'As credenciais informadas não conferem.',
            ]);
        }

        RateLimiter::clear($this->throttleKey());
    }

    /**
     * @throws ValidationException
     */
    private function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), self::MAX_ATTEMPTS)) {
            return;
        }

        Event::dispatch(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'email' => "Muitas tentativas de acesso. Tente novamente em {$seconds} segundos.",
        ]);
    }

    /**
     * Chave do limitador: e-mail somado ao IP de origem.
     *
     * Só o e-mail permitiria que um atacante bloqueasse a conta de outra
     * pessoa de propósito; só o IP bloquearia todos os usuários atrás de um
     * mesmo NAT por causa de um deles.
     */
    private function throttleKey(): string
    {
        return Str::transliterate(
            Str::lower($this->string('email')->value()).'|'.$this->ip()
        );
    }
}
