<div class="col-md-8">
    <div class="row">
        <div class="col-xl-6 col-lg-6 col-md-6 col-sm-12">

            <div class="card mb-4">
                <div class="card-body">
                    <b> Descricao: </b>{!! nl2br(htmlspecialchars($order->description)) !!}<br>
                    @if (trim($order->attachment) != '')
                        <b>Anexo: </b><a href="{{'./storage/uploads/'.$order->attachment}}" download> Clique aqui</a> <br>
                    @endif

                </div>
            </div>
            <div class="card mb-4">
                <div class="card-body">
                    <b>Patrimônio: </b>{{ $order->tag }}<br>
                    @if ($canEditTag)
                        <button id="botao-editar-patrimonio" type="button" acao="editar_patrimonio"
                            class="dropdown-item text-right" data-toggle="modal" data-target="#modalStatus">
                            Editar Patrimônio
                        </button>
                    @endif

                </div>
            </div>
            <div class="card mb-4">
                <div class="card-body">
                    <b>Solucao: </b>{!! nl2br(htmlspecialchars($order->solution)) !!}<br>
                    @if ($canEditSolution)
                        <button id="botao-editar-solucao" type="button" acao="editar_solucao"
                            class="dropdown-item text-right" data-toggle="modal" data-target="#modalStatus">
                            Editar Solução
                        </button>
                    @endif



                </div>
            </div>
        </div>
        <div class="col-xl-6 col-lg-6 col-md-6 col-sm-12">
            <div class="card mb-4">
                <div class="card-body">
                    <b>Serviço Solicitado: </b>{{ $order->service->name }} - {{ $order->service->description }}<br>
                    @if ($order->canEditService)
                        <button type="button" id="botao-editar-servico" acao="editar_servico"
                            class="dropdown-item text-right" data-toggle="modal" data-target="#modalStatus">
                            Editar Serviço
                        </button>
                    @endif
                    <hr>
                    @if (!$isLevelClient)
                        @if ($order->service->sla > 1)
                            <b>SLA: </b>{{ $order->service->sla }} Horas úteis<br>
                        @endif
                        @if ($order->service->sla === 1)
                            <b>SLA: </b> 1 Hora útil<br>
                        @endif
                        @if ($order->service->sla === 0)
                            SLA não definido. <br>
                        @endif

                    @endif
                    <b>Data de Abertura: </b>{{ date('d/m/Y', strtotime($order->created_at)) }}
                    {{ date('H', strtotime($order->created_at)) }}h{{ date('i', strtotime($order->created_at)) }}min<br>

                    <span class="{{ $isLate ? 'text-danger' : '' }}">
                        <b>Solução Estimada: </b>
                        {{ date('d/m/Y', strtotime($dataSolucao)) }}
                        {{ date('H', strtotime($dataSolucao)) }}h{{ date('i', strtotime($dataSolucao)) }}min
                    </span><br>


                </div>
            </div>
            <div class="card mb-4">
                <div class="card-body">
                    <b>Requisitante: </b>{{ $order->customer != null ? $order->customer->name : "Não Informado" }}<br />
                    <b>Setor do Requisitante:</b>{{ $order->division_sig }}<br>
                    <b>Campus: </b>{{ $order->campus }} <br>
                    <b>Email: </b>{{ $order->email }}<br>
                    <b>Local/Sala: </b>{{ $order->place }}<br>
                    <b>Ramal: </b>{{ $order->phone_number }}<br>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-body">
                    <b>Setor Responsável: </b>{{ $order->division->name }}<br>
                    <b>Técnico Responsável: </b>{{ (($order->status != 'opened' && $order->provider != null) ? $order->provider->name : "Não informado") }}<br>
                </div>
            </div>
        </div>
    </div>
</div>
@include('orders.panel-status-logs')