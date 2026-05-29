<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\CustomerPaymentMethodFee;
use App\Models\Tpa;
use Illuminate\Http\Request;

class CustomerTpaController extends Controller
{
    public function destroy(Customer $customer, Tpa $tpa)
    {
        abort_unless($tpa->customer_id === $customer->id, 403);

        $customer->tokens()->where('name', 'tpa_login_' . $tpa->serial)->delete();
        $tpa->delete();

        return back()->with('success', 'TPA removido.');
    }

    public function storeFee(Request $request, Customer $customer, Tpa $tpa)
    {
        abort_unless($tpa->customer_id === $customer->id, 403);

        $data = $request->validate([
            'payment_method'  => 'required|string|max:50',
            'label'           => 'required|string|max:100',
            'fee_percentage'  => 'required|numeric|min:0|max:1',
            'fee_fixed'       => 'required|numeric|min:0',
            'currency'        => 'required|string|size:3',
            'is_active'       => 'boolean',
        ]);

        CustomerPaymentMethodFee::updateOrCreate(
            [
                'customer_id'    => $customer->id,
                'tpa_id'         => $tpa->id,
                'payment_method' => $data['payment_method'],
                'currency'       => strtoupper($data['currency']),
            ],
            [
                'label'          => $data['label'],
                'fee_percentage' => $data['fee_percentage'],
                'fee_fixed'      => $data['fee_fixed'],
                'is_active'      => $request->boolean('is_active', true),
            ]
        );

        return back()->with('success', 'Taxa guardada.');
    }

    public function destroyFee(Customer $customer, Tpa $tpa, CustomerPaymentMethodFee $fee)
    {
        abort_unless($tpa->customer_id === $customer->id, 403);
        abort_unless($fee->tpa_id === $tpa->id, 403);

        $fee->delete();

        return back()->with('success', 'Taxa removida.');
    }
}
