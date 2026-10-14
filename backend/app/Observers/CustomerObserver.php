<?php

declare(strict_types=1);

namespace App\Observers;

use App\Enums\AuditEvent;
use App\Models\Customer;
use App\Models\CustomerAudit;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

/**
 * Alimenta a trilha de auditoria a partir dos eventos do model.
 *
 * Um observador, e não chamadas espalhadas pelo controller, porque auditoria
 * esquecida é pior que auditoria ausente: dá a impressão de haver registro
 * completo quando há lacunas. Ligado ao ciclo de vida do model, nenhum
 * caminho de alteração escapa — nem seeder, nem comando de console, nem um
 * endpoint futuro que alguém acrescente sem lembrar da trilha.
 */
final class CustomerObserver
{
    public function created(Customer $customer): void
    {
        $this->record($customer, AuditEvent::Created);
    }

    /**
     * Distingue alteração de cadastro de mudança de situação.
     *
     * Inativar e reativar alteram um único campo, `is_active`, e registrar
     * isso como "alterado" esconderia a informação que mais importa numa
     * trilha: que o cliente deixou de ser atendido, e quando.
     */
    public function updated(Customer $customer): void
    {
        $alterados = array_keys($customer->getChanges());

        // `updated_at` muda em toda escrita e não diz nada sobre o que o
        // operador fez.
        $alterados = array_values(array_diff($alterados, ['updated_at']));

        if ($alterados === []) {
            return;
        }

        if ($alterados === ['is_active']) {
            $this->record(
                $customer,
                $customer->is_active ? AuditEvent::Reactivated : AuditEvent::Deactivated,
            );

            return;
        }

        $this->record($customer, AuditEvent::Updated, $alterados);
    }

    /**
     * Eliminação definitiva.
     *
     * Registrado antes de a linha sumir, e a trilha permanece depois — é ela
     * que comprova o atendimento ao direito do titular.
     */
    public function deleted(Customer $customer): void
    {
        $this->record($customer, AuditEvent::Deleted);
    }

    /**
     * @param  list<string>|null  $changedFields
     */
    private function record(Customer $customer, AuditEvent $event, ?array $changedFields = null): void
    {
        $audit = new CustomerAudit;

        $audit->customer_id = $customer->id;

        // `Auth::id()` é tipado como int|string|null, porque o identificador
        // do usuário pode ser um UUID em outras aplicações. Aqui é bigint, e
        // a conversão deixa isso explícito em vez de confiar na coincidência.
        $identificador = Auth::id();
        $audit->user_id = is_numeric($identificador) ? (int) $identificador : null;

        $audit->event = $event;

        // Apenas os NOMES dos campos. Guardar os valores duplicaria dado
        // pessoal numa segunda tabela e inviabilizaria a eliminação.
        $audit->changed_fields = $changedFields;

        $audit->ip_address = Request::ip();

        $audit->save();
    }
}
