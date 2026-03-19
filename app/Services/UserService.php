<?php
namespace App\Services;

use Illuminate\Support\Facades\Hash;

class UserService
{
   public function update(array $data)
   {
       $user = auth()->user();
       if (isset($data['password'])) {
           $data['password'] = Hash::make($data['password']);
       }
       $user->update($data);
       return $user;
   }
}
