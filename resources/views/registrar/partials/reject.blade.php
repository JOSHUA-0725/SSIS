<form method="POST" action="{{ route('registrar.requests.update', $docRequest) }}" class="row g-2 align-items-end border-top pt-3" data-confirm="Reject this request? The student will see the reason.">
    @csrf
    @method('PUT')
    <input type="hidden" name="action" value="reject">
    <div class="col">
        <label for="reason" class="form-label">Reason for rejection</label>
        <input type="text" id="reason" name="reason" class="form-control" maxlength="255" value="{{ old('reason') }}" required>
    </div>
    <div class="col-auto"><button type="submit" class="btn btn-outline-danger">Reject</button></div>
</form>
