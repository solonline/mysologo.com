<?php
namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EmailValidationController extends Controller
{
    public function verifyFrontend(Request $request, $id, $hash)
    {
        // dd($request->all());
        $redirect = $request->query('redirect', '/');
        $name = $request->query('name', '');

        if (!$id || !$hash) {
            return response()->json(['message' => 'Invalid parameters'], 400);
        }

        $customer = Customer::find($id);
       
        if (!$customer) {
            return response()->json(['message' => 'User not found'], 404);
        }
        if (sha1($customer->email) !== $hash) {
            return response()->json(['message' => 'Invalid hash'], 400);
        }
        if ($customer->hasVerifiedEmail()) {
            return response()->json(['status' => 'already_verified', 'name' => $customer->name], 409);
        }
        $customer->markEmailAsVerified();
        return response()->json(['status' => 'success', 'name' => $customer->name]);
    }
}
