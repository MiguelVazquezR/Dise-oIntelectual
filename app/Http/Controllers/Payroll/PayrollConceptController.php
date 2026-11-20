<?php

namespace App\Http\Controllers\Payroll;

use App\Enums\PayrollConceptType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Payroll\IndexPayrollConceptRequest;
use App\Http\Requests\Payroll\StorePayrollConceptRequest;
use App\Http\Requests\Payroll\UpdatePayrollConceptRequest;
use App\Models\PayrollConcept;
use App\Models\PayrollItemConcept;
use App\Models\User;
use App\Services\Payroll\PayrollAuditRecorder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Catálogo de conceptos de nómina (§7.4).
 *
 * CRUD con `code` único. Los conceptos en uso por ajustes o por conceptos de
 * items calculados no se eliminan: se desactivan (`is_active=false`) para no
 * romper la trazabilidad histórica.
 */
class PayrollConceptController extends Controller
{
    /**
     * Aviso permanente de la página (§7.5): los conceptos son control interno.
     */
    public const NOTICE = 'Los conceptos son internos de control; no representan cálculos fiscales.';

    public function __construct(private readonly PayrollAuditRecorder $auditor) {}

    public function index(IndexPayrollConceptRequest $request): Response
    {
        /** @var User $user */
        $user = $request->user();
        $filters = $request->validated();

        $concepts = PayrollConcept::query()
            ->when($filters['search'] ?? null, function (Builder $query, string $search): void {
                $query->where(function (Builder $inner) use ($search): void {
                    $inner->where('code', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%");
                });
            })
            ->when($filters['type'] ?? null, fn (Builder $query, string $type) => $query->where('type', $type))
            ->when(array_key_exists('is_active', $filters), fn (Builder $query) => $query->where('is_active', (bool) $filters['is_active']))
            ->orderBy('code')
            ->get()
            ->map(fn (PayrollConcept $concept): array => [
                'id' => $concept->id,
                'code' => $concept->code,
                'name' => $concept->name,
                'type' => $concept->type->value,
                'calculation' => $concept->calculation->value,
                'default_amount' => $concept->default_amount !== null ? (string) $concept->default_amount : null,
                'is_sensitive' => $concept->is_sensitive,
                'is_active' => $concept->is_active,
                'notes' => $concept->notes,
            ])
            ->values()
            ->all();

        return Inertia::render('concepts/Index', [
            'filters' => [
                'search' => $filters['search'] ?? null,
                'type' => $filters['type'] ?? null,
                'is_active' => array_key_exists('is_active', $filters) ? (bool) $filters['is_active'] : null,
            ],
            'concepts' => $concepts,
            'notice' => self::NOTICE,
            'options' => [
                'types' => array_map(
                    fn (PayrollConceptType $type): array => ['value' => $type->value, 'label' => $type->label()],
                    PayrollConceptType::cases(),
                ),
            ],
            'can' => [
                'manage' => $user->hasPermission('concepts.manage'),
            ],
        ]);
    }

    public function store(StorePayrollConceptRequest $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $data = $request->validated();

        $concept = PayrollConcept::query()->create([
            'code' => (string) $data['code'],
            'name' => (string) $data['name'],
            'type' => $data['type'],
            'calculation' => $data['calculation'],
            'default_amount' => $data['default_amount'] ?? null,
            'is_sensitive' => (bool) ($data['is_sensitive'] ?? false),
            'is_active' => (bool) ($data['is_active'] ?? true),
            'notes' => $data['notes'] ?? null,
        ]);

        $this->auditor->conceptChanged('payroll.concept.created', $concept, $user, null, $request->ip(), $request->userAgent());

        return back()->with('success', 'Concepto '.$concept->code.' creado correctamente.');
    }

    public function update(UpdatePayrollConceptRequest $request, PayrollConcept $concept): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $data = $request->validated();
        $before = [
            'code' => $concept->code,
            'type' => $concept->type->value,
            'calculation' => $concept->calculation->value,
            'is_sensitive' => $concept->is_sensitive,
            'is_active' => $concept->is_active,
        ];

        $concept->update([
            'code' => (string) $data['code'],
            'name' => (string) $data['name'],
            'type' => $data['type'],
            'calculation' => $data['calculation'],
            'default_amount' => $data['default_amount'] ?? null,
            'is_sensitive' => (bool) ($data['is_sensitive'] ?? false),
            'is_active' => (bool) ($data['is_active'] ?? true),
            'notes' => $data['notes'] ?? null,
        ]);

        $this->auditor->conceptChanged('payroll.concept.updated', $concept->refresh(), $user, $before, $request->ip(), $request->userAgent());

        return back()->with('success', 'Concepto actualizado correctamente.');
    }

    public function destroy(PayrollConcept $concept): RedirectResponse
    {
        if ($concept->adjustments()->exists()) {
            return back()->with('error', 'No se puede eliminar el concepto: tiene ajustes asociados. Desactívalo en su lugar.');
        }

        if (PayrollItemConcept::query()->where('code', $concept->code)->exists()) {
            return back()->with('error', 'No se puede eliminar el concepto: aparece en pre-nóminas calculadas. Desactívalo en su lugar.');
        }

        /** @var User $user */
        $user = request()->user();
        $before = [
            'code' => $concept->code,
            'type' => $concept->type->value,
            'calculation' => $concept->calculation->value,
            'is_sensitive' => $concept->is_sensitive,
            'is_active' => $concept->is_active,
        ];

        $concept->delete();

        $this->auditor->conceptChanged('payroll.concept.deleted', $concept, $user, $before);

        return back()->with('success', 'Concepto eliminado correctamente.');
    }
}
