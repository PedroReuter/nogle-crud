<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\CustomerAudit;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CustomerAudit
 */
final class CustomerAuditResource extends JsonResource
{
    /**
     * Um evento da trilha de auditoria.
     *
     * Não expõe o endereço de origem: ele existe para investigação de acesso
     * indevido, feita com acesso ao banco, e devolvê-lo na listagem
     * espalharia dado de operador por uma tela que não precisa dele.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'event' => $this->event->value,
            'event_label' => $this->event->label(),

            // Nomes de campos, nunca valores — ver a migration da tabela.
            'changed_fields' => $this->changed_fields,

            // O operador pode ter sido removido depois do evento; a trilha
            // permanece.
            'user' => $this->whenLoaded('user', fn (): ?array => $this->user === null ? null : [
                'id' => $this->user->id,
                'name' => $this->user->name,
            ]),

            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
