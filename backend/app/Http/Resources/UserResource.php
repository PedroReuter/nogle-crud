<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin User
 */
final class UserResource extends JsonResource
{
    /**
     * Dados do usuário autenticado.
     *
     * Deliberadamente mínimo: nome e e-mail para identificar quem está na
     * sessão, e as permissões derivadas do perfil. Campos como
     * `email_verified_at` ou `created_at` não têm uso na interface, e cada
     * campo exposto sem necessidade é dado pessoal a mais trafegando.
     *
     * As permissões vêm resolvidas em vez de o frontend inferi-las do papel.
     * Se um terceiro perfil surgir, a interface não precisa aprender a
     * regra — ela já chega decidida pelo mesmo lugar que a Policy consulta.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'role' => $this->role->value,
            'role_label' => $this->role->label(),
            'permissions' => [
                'manage_customers' => $this->role->canManageCustomers(),
            ],
        ];
    }
}
