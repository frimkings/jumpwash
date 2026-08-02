@extends('layouts.app')

@section('title', $title)

@section('content')
<div class="card-surface p-8">
    <p class="text-sm uppercase tracking-[0.3em] text-cyan-300">Module scaffold</p>
    <h3 class="mt-2 text-2xl font-semibold text-white">{{ $title }}</h3>
    <p class="mt-4 max-w-2xl text-sm leading-6 text-slate-300">
        This module route is wired and branch-scoped. The next iteration can add Livewire-driven CRUD, barcode generation,
        thermal receipt templates, exports, and role-based access control.
    </p>
</div>
@endsection
