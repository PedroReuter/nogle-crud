<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Requests\IndexCustomerRequest;
use App\Http\Requests\StoreCustomerRequest;
use App\Http\Requests\UpdateCustomerRequest;
use App\Http\Resources\CustomerAuditResource;
use App\Http\Resources\CustomerResource;
use App\Models\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

/**
 * Operações de cliente.
 *
 * O controller permanece enxuto de propósito: a validação está nos Form
 * Requests, a montagem da consulta nos escopos do model e a serialização
 * no Resource. Não existe camada de Service nem Repository entre ele e o
 * Eloquent porque não há, hoje, regra de negócio a isolar — cadastrar um
 * cliente é validar e gravar. Interpor uma classe que apenas repassa
 * `Customer::create()` acrescentaria um arquivo a ler sem acrescentar
 * nada a entender. Quando surgir regra com substância, ela terá um
 * Service com motivo.
 */
final class CustomerController implements HasMiddleware
{
    /**
     * Autorização de cada endpoint, declarada como middleware.
     *
     * Middleware, e não `Gate::authorize()` no corpo do método, por uma
     * razão de ordem: o Form Request é resolvido — e portanto valida — na
     * injeção de dependência do método, que acontece depois do corpo ter
     * sido alcançado. Verificar ali faria um usuário sem permissão receber
     * 422 com os erros de validação antes de receber 403, revelando as
     * regras do formulário a quem não pode usá-lo. O middleware roda antes
     * de qualquer validação.
     *
     * Também não usa `authorizeResource()`: inativar e reativar não têm
     * equivalente no mapa padrão de um resource, e metade das verificações
     * implícitas com a outra metade explícita seria pior de auditar do que
     * todas visíveis nesta lista.
     *
     * O segundo argumento de `can:` é o nome do parâmetro de rota, de modo
     * que a Policy recebe o próprio cliente — e não apenas a classe — nas
     * operações que agem sobre um registro.
     *
     * @return array<int, Middleware>
     */
    public static function middleware(): array
    {
        return [
            new Middleware('can:viewAny,'.Customer::class, only: ['index']),
            new Middleware('can:view,customer', only: ['show']),
            new Middleware('can:create,'.Customer::class, only: ['store']),
            new Middleware('can:update,customer', only: ['update']),
            new Middleware('can:deactivate,customer', only: ['deactivate']),
            new Middleware('can:reactivate,customer', only: ['reactivate']),
            new Middleware('can:view,customer', only: ['audits']),
            new Middleware('can:forceDelete,customer', only: ['destroy']),
        ];
    }

    /**
     * GET /api/customers
     *
     * Filtros combináveis por nome e CPF, com paginação server-side.
     */
    public function index(IndexCustomerRequest $request): AnonymousResourceCollection
    {

        $filters = $request->filters();

        $customers = Customer::query()
            ->whereNameMatches($filters->name)
            ->whereCpfStartsWith($filters->cpf)
            ->whereStatus($filters->status)
            ->sortedBy($filters->sort, $filters->direction)
            ->paginate($filters->perPage)
            // Preserva os filtros nos links de paginação, para que a
            // página 2 de uma busca continue filtrada.
            ->withQueryString();

        return CustomerResource::collection($customers);
    }

    /**
     * POST /api/customers
     */
    public function store(StoreCustomerRequest $request): JsonResponse
    {

        // `validated()` em vez de `all()`: apenas os campos que passaram
        // pelas regras chegam ao model, e o `$fillable` barra o que
        // escapasse daqui. São duas barreiras contra mass assignment,
        // porque uma delas pode ser afrouxada por engano no futuro.
        $customer = Customer::create($request->validated());

        return CustomerResource::make($customer)
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * GET /api/customers/{customer}
     */
    public function show(Customer $customer): CustomerResource
    {

        return CustomerResource::make($customer);
    }

    /**
     * PUT|PATCH /api/customers/{customer}
     */
    public function update(UpdateCustomerRequest $request, Customer $customer): CustomerResource
    {

        $customer->update($request->validated());

        return CustomerResource::make($customer);
    }

    /**
     * PATCH /api/customers/{customer}/deactivate
     *
     * Inativação lógica: o registro permanece no banco, como exige o
     * requisito. Idempotente — inativar um cliente já inativo devolve 200
     * com o estado atual, em vez de erro, porque o resultado pretendido
     * pelo cliente da API já é o vigente.
     */
    public function deactivate(Customer $customer): CustomerResource
    {

        // Atribuição direta, não `update(['is_active' => ...])`: a coluna
        // está deliberadamente fora do `$fillable` para que nenhum `PUT`
        // consiga alterar a situação do cliente, e a atribuição em massa
        // descartaria o valor em silêncio.
        $customer->is_active = false;
        $customer->save();

        return CustomerResource::make($customer);
    }

    /**
     * PATCH /api/customers/{customer}/reactivate
     */
    public function reactivate(Customer $customer): CustomerResource
    {

        $customer->is_active = true;
        $customer->save();

        return CustomerResource::make($customer);
    }

    /**
     * GET /api/customers/{customer}/audits
     *
     * Histórico de quem alterou o quê e quando. Atende ao princípio de
     * responsabilização da LGPD: é o que permite demonstrar o tratamento
     * dado aos dados pessoais.
     */
    public function audits(Customer $customer): AnonymousResourceCollection
    {
        $audits = $customer->audits()
            // `with` evita uma consulta por evento para buscar o operador.
            ->with('user:id,name')
            // Desempate por id: `created_at` tem precisão de segundo, e dois
            // eventos no mesmo segundo — cadastrar e inativar em seguida, por
            // exemplo — ficariam em ordem indefinida. Numa trilha de
            // auditoria, ordem ambígua é defeito: ela existe para dizer o que
            // veio antes.
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(20);

        return CustomerAuditResource::collection($audits);
    }

    /**
     * DELETE /api/customers/{customer}
     *
     * Eliminação definitiva, em atendimento ao direito do titular previsto
     * na LGPD.
     *
     * Não é o oposto de cadastrar, e não substitui a inativação: o requisito
     * do desafio pede que inativar não apague, e continua sendo assim. Esta
     * operação existe para outro propósito — quando o titular pede que seus
     * dados sejam eliminados, inativar não basta, porque o CPF, o telefone e
     * o endereço continuariam armazenados.
     *
     * A Policy exige que o cliente já esteja inativo, o que torna a
     * eliminação deliberada em dois passos.
     *
     * A trilha de auditoria registra o evento e sobrevive ao registro
     * apagado — é ela que comprova que a eliminação foi atendida.
     */
    public function destroy(Customer $customer): Response
    {
        $customer->delete();

        return response()->noContent();
    }
}
