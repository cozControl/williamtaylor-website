@props(['errors'])

@if (session('status'))
    <div class="admin-flash admin-flash-success" role="status">
        <span aria-hidden="true">&#10003;</span>
        <strong>{{ session('status') }}</strong>
    </div>
@endif

@if ($errors->any())
    <div class="admin-flash admin-flash-error" role="alert">
        <strong>Please correct the highlighted fields.</strong>
    </div>
@endif
