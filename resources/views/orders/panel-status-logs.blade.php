<aside class="col-md-4 blog-sidebar">
    <h4 class="font-italic">Histórico</h4>
    <div class="container">
        @foreach ($order->statusLogs as $status)

            <div class="notice {{ $listColorStatus[$status->status]}}">
                <strong>{{ __($status->status) }} </strong><br>
                @if ($status->status == 'committed')
                    <br>
                    @for ($i = 0; $i < intval($order->rating); $i++)
                        <img class="m-2 estrela-1" nota="1" src="{{ asset('img/star1.png') }}"
                            alt="1">
                    @endfor
                @endif

                <br>{{ $status->message }}<br>
                <strong>{{ $status->user->name }}<br>{{ date('d/m/Y - H:i', strtotime($status->created_at)) }}</strong>
            </div>
        @endforeach
    </div>
</aside>