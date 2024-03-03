@php
    $bgCard = '';
    $link = 'text-light font-weight-bold p-3';
    $texto = 'text-black-50';
    $styleInline = '';

    switch ($order->status) {
        case 'opened':
            $bgCard = 'bg-warning';
            $texto = 'text-light';
            break;
        case 'reopened':
            $bgCard = 'bg-warning';
            $texto = 'text-light';
            break;
        case 'in progress':
            $bgCard = 'bg-info';
            $texto = 'text-light';
            break;
        case 'closed':
            $bgCard = 'bg-success';
            $texto = 'text-light';
            break;
        case 'committed':
            $bgCard = 'bg-success';
            $texto = 'text-light';
            break;
        case 'canceled':
            $bgCard = 'bg-light';
            $texto = 'text-dark';
            break;
        case 'reserved':
            $bgCard = 'bg-secondary';
            $texto = 'text-light';
            break;
        case 'pending customer response':
            $styleInline = 'background-color:  #993399;';
            $bgCard = '';
            $texto = 'text-light';
            break;
        case 'pending resource':
            $styleInline = 'background-color:  #F85D10;';
            $bgCard = '';
            $texto = 'text-light';
            break;
    }

    $providerName = '';
    $customerName = '';

    if ($order->provider_name != null) {
        $nameParts = explode(' ', $order->provider_name);
        $firstName = ucfirst(mb_strtolower($nameParts[0], 'UTF-8'));
        $lastName = ucfirst(mb_strtolower(end($nameParts), 'UTF-8'));
        $providerName = $firstName . ' ' . $lastName;
    }
    if ($order->customer_name != null) {
        $nameParts = explode(' ', $order->customer_name);
        $firstName = ucfirst(mb_strtolower($nameParts[0], 'UTF-8'));
        $lastName = ucfirst(mb_strtolower(end($nameParts), 'UTF-8'));
        $customerName = $firstName . ' ' . $lastName;
    }
@endphp


<div class="col-sm-12 col-md-12 col-xl-6">
    <div class="card draggable shadow-sm  {{ $bgCard }}" style="height: 260px;{{ $styleInline }}">
        <div class="card-body p-2">
            <div class="card-title">

                <a href="./?page=ocorrencia&selecionar={{ $order->id }}" class="text-light font-weight-bold p-3">
                    #{{ $order->id }}
                </a>
            </div>
            <p class="{{ $texto }}">
                {{ substr($order->descricao, 0, 75) }}[...]
            </p>
            @if ($customerName != null)
                <small class="{{ $texto }}">Demandante: {{ $customerName }}</small><br>
            @endif

            <small class="{{ $texto }}">{{ __($order->status) }}</small>
            @if ($providerName != null)
                <br><small class="{{ $texto }}">Responsável: {{ $providerName }}</small>
            @endif
            <br><small class="{{ $texto }}">Aberto em {{ date('d/m/Y G:i:s', strtotime($order->created_at)) }}
            </small>
            @if ($order->finished_at != null)
                <br><small class="{{ $texto }}">Fechado em
                    {{ date('d/m/Y G:i:s', strtotime($order->finished_at)) }}</small>
            @endif

        </div>
    </div>
</div>
