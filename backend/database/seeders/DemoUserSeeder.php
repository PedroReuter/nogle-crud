<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Usuários de demonstração, um por perfil.
 *
 * As senhas são fracas e públicas de propósito: existem para o avaliador
 * entrar na aplicação. Elas estão documentadas no README e valem apenas
 * para o ambiente de demonstração — nenhuma credencial real do projeto
 * está no repositório, conforme exige o requisito de segurança.
 */
final class DemoUserSeeder extends Seeder
{
    public function run(): void
    {
        $this->createUser(
            name: 'Administradora Demo',
            email: 'admin@nogle.test',
            role: UserRole::Admin,
        );

        $this->createUser(
            name: 'Leitor Demo',
            email: 'leitura@nogle.test',
            role: UserRole::Viewer,
        );
    }

    /**
     * `firstOrCreate` em vez de `create`: rodar o seeder duas vezes é algo
     * que acontece, e falhar na constraint de e-mail único transformaria
     * uma operação inofensiva em erro.
     */
    private function createUser(string $name, string $email, UserRole $role): void
    {
        $user = User::firstOrCreate(
            ['email' => $email],
            ['name' => $name, 'password' => 'senha123', 'email_verified_at' => now()],
        );

        // `role` está fora do `$fillable`, então é atribuída explicitamente.
        if ($user->role !== $role) {
            $user->role = $role;
            $user->save();
        }
    }
}
