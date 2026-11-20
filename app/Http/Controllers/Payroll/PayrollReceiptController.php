<?php

namespace App\Http\Controllers\Payroll;

use App\Http\Controllers\Controller;
use App\Models\PayrollItem;
use App\Models\User;
use App\Services\Payroll\PayrollReceiptBuilder;
use App\Support\BranchAccess;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Recibo imprimible de la pre-nómina (§7.4).
 *
 * Documento preliminar: sin UUID, sellos, RFC ni datos fiscales. Se renderiza
 * como página imprimible con `window.print()` en el cliente; no se generan PDF
 * en el servidor.
 */
class PayrollReceiptController extends Controller
{
    public function __construct(private readonly PayrollReceiptBuilder $builder) {}

    public function show(Request $request, PayrollItem $item): Response
    {
        /** @var User $user */
        $user = $request->user();
        $item->loadMissing(['run.period', 'run.branch']);

        abort_unless(BranchAccess::allows($user, (int) $item->run->branch_id), 403);

        return Inertia::render('payroll/Receipt', $this->builder->build($item));
    }
}
