@extends('layouts.app')

@section('content')
    @include('admin.navbar')
    <div class="container">
        <div class="row">


            <div class="col-md-9">
                <div class="card">
                    <div class="card-header">Create New Order</div>
                    <div class="card-body">
                        <a href="{{ url('/orders') }}" title="Back"><button class="btn btn-warning btn-sm"><i class="fa fa-arrow-left" aria-hidden="true"></i> Back</button></a>
                        <br />
                        <br />

                        @if ($errors->any())
                            <ul class="alert alert-danger">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        @endif

                        {!! Form::open(['url' => '/orders', 'class' => 'form-horizontal', 'files' => true]) !!}

                        @include ('orders.form', ['formMode' => 'create'])

                        {!! Form::close() !!}

                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
