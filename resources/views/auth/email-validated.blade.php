@extends('layouts.app')

@section('content')
<div class="container mt-5">
    <div class="card">
        <div class="card-body text-center">
            <h2>Email validado com sucesso!</h2>
            <p>Olá, {{ $customer->name ?? $customer->email }}. Seu email foi validado.</p>
        </div>
    </div>
</div>
@endsection
