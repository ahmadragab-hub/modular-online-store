@if (session('status') || session('cart_status'))
    <div class="sf-container pt-4">
        <p class="sf-alert border-[var(--sf-border)] bg-[var(--sf-surface)] text-[var(--sf-text)]" role="status">
            {{ session('status') ?? session('cart_status') }}
        </p>
    </div>
@endif

@if ($errors->any())
    <div class="sf-container pt-4">
        <div class="sf-alert sf-alert--error" role="alert">
            <ul class="list-disc ps-5">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    </div>
@endif