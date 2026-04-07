<?php
namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EmailValidationController extends Controller
{
    public function show(Request $request)
    {
        $redirect = $request->query('redirect', '/');

        // Verifica se a URL é válida (assinada)
        if (!$request->hasValidSignature()) {
            return redirect($redirect . '?status=expired');
        }

        $customer = Customer::findOrFail($request->query('id'));

        if ($customer->hasVerifiedEmail()) {
            return redirect($redirect . '?status=already_verified&name=' . urlencode($customer->name));
        }
        $customer->markEmailAsVerified();

        return redirect($redirect . '?status=success&name=' . urlencode($customer->name));
    }
}
