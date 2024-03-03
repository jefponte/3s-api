<div class="container-fluid">
    <div class="row flex-row flex-sm-nowrap">
        <div class="col-sm-6 col-md-4 col-xl-4">

            <div class="card bg-light">
                <div class="card-body">
                    <h6 class="card-title text-uppercase text-truncate py-2">Chamados Abertos
                        ({{ count($penddingList) }})</h6>
                    <div class="items border border-light">
                        <div class="row">

                            @foreach ($penddingList as $order)
                                @include('admin.partials.card-kanban', ['order' => $order])
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-md-4 col-xl-4">
            <div class="card bg-light">
                <div class="card-body">
                    <h6 class="card-title text-uppercase text-truncate py-2">Em Atendimento
                        ({{ count($progressList) }})</h6>
                    <div class="items border border-light">



                        <div class="row">

                            @foreach ($progressList as $order)
                                @include('admin.partials.card-kanban', [
                                    'order' => $order,
                                ])
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-md-4 col-xl-4">
            <div class="card bg-light">
                <div class="card-body">
                    <h6 class="card-title text-uppercase text-truncate py-2">Fechado</h6>
                    <div class="items border border-light">
                        <div class="row">

                            @foreach ($finishedList as $order)
                                @include('admin.partials.card-kanban', [
                                    'order' => $order,
                                ])
                            @endforeach

                        </div>

                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
