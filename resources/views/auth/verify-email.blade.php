@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">Verifique seu endereço de e-mail</div>
                <div class="card-body">
                    @if (session('resent'))
                        <div class="alert alert-success" role="alert">
                            Um novo link de verificação foi enviado para seu endereço de e-mail.
                        </div>
                    @endif
                    Antes de prosseguir, por favor verifique seu e-mail para um link de verificação.<br>
                    Se você não recebeu o e-mail,
                    <form class="d-inline" method="POST" action="{{ route('verification.send') }}">
                        @csrf
                        <button type="submit" class="btn btn-link p-0 m-0 align-baseline">clique aqui para solicitar outro</button>.
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
