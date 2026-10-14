<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AuditEvent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Registro da trilha de auditoria.
 *
 * @property int $id
 * @property int $customer_id
 * @property int|null $user_id
 * @property AuditEvent $event
 * @property list<string>|null $changed_fields
 * @property string|null $ip_address
 * @property Carbon $created_at
 */
class CustomerAudit extends Model
{
    /**
     * A trilha não tem `updated_at`: um registro de auditoria que pudesse ser
     * alterado não serviria como evidência.
     */
    public const UPDATED_AT = null;

    /**
     * Sem `$fillable`: estes registros são criados apenas pelo observador,
     * nunca a partir de dados de requisição.
     *
     * @var list<string>
     */
    protected $fillable = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'event' => AuditEvent::class,
            'changed_fields' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /**
     * O cliente auditado.
     *
     * Pode não existir mais: a trilha sobrevive à eliminação, que é
     * justamente o evento que ela comprova.
     *
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
