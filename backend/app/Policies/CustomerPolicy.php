<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Customer;
use App\Models\User;

/**
 * Autorização das operações de cliente.
 *
 * Concentra em um único lugar a resposta a "quem pode fazer o quê", de modo
 * que a regra não fique espalhada por controllers e Form Requests — onde
 * acabaria aplicada em alguns pontos e esquecida em outros.
 *
 * Os dois perfis do requisito mapeiam assim:
 *
 *   Administrador — consulta, cadastra, edita, inativa e reativa.
 *   Leitura       — apenas consulta e filtra.
 *
 * A interface esconde as ações que o perfil de leitura não pode executar,
 * mas isso é conveniência visual. A garantia está aqui: uma requisição
 * forjada direto à API encontra a mesma verificação.
 */
final class CustomerPolicy
{
    /** Listar e filtrar: permitido a qualquer usuário autenticado. */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /** Consultar um cliente específico. */
    public function view(User $user, Customer $customer): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->role->canManageCustomers();
    }

    public function update(User $user, Customer $customer): bool
    {
        return $user->role->canManageCustomers();
    }

    /**
     * Inativar e reativar são autorizações próprias, e não casos de
     * `update`.
     *
     * Separá-las custa duas linhas agora e permite, sem refatoração, um
     * perfil futuro que possa editar cadastro mas não desativar clientes —
     * uma distinção comum em sistemas administrativos. Mais imediatamente,
     * deixa explícito no controller qual permissão cada endpoint exige.
     */
    public function deactivate(User $user, Customer $customer): bool
    {
        return $user->role->canManageCustomers();
    }

    public function reactivate(User $user, Customer $customer): bool
    {
        return $user->role->canManageCustomers();
    }
}
