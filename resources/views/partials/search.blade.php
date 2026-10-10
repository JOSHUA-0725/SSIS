<form method="GET" class="row g-2 mb-3" role="search">
    @foreach ($keep ?? [] as $name => $value)
        <input type="hidden" name="{{ $name }}" value="{{ $value }}">
    @endforeach
    <div class="col">
        <label for="q" class="visually-hidden">{{ $placeholder }}</label>
        <input type="search" id="q" name="q" class="form-control" value="{{ $q }}" placeholder="{{ $placeholder }}">
    </div>
    <div class="col-auto"><button type="submit" class="btn btn-outline-primary">Search</button></div>
</form>
