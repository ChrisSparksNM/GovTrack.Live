@extends('layouts.app')

@section('title', 'Congressional Bills')

@section('content')
<div class="container mx-auto px-4 py-8">
    <h1>Congressional Bills</h1>
    <p>This is a minimal test version.</p>
    
    @if(isset($bills) && $bills->count() > 0)
        <div class="space-y-4">
            @foreach($bills as $bill)
                <div class="bg-white p-4 rounded shadow">
                    <h3>{{ $bill->congress_id }}</h3>
                    <p>{{ $bill->title }}</p>
                </div>
            @endforeach
        </div>
    @else
        <p>No bills found.</p>
    @endif
</div>
@endsection