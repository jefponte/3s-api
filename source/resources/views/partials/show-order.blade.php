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
                    @if ($canEditService)
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

                    @if ($canRequestHelp)
                        <button type="button" id="botao-pedir-ajuda" class="dropdown-item text-right"
                            data-toggle="modal" data-target="#modalPedirAjuda">
                            Pedir Ajuda
                        </button>
                        <div class="modal fade" id="modalPedirAjuda" tabindex="-1" role="dialog"
                            aria-labelledby="labelPedirAjuda" aria-hidden="true">
                            <div class="modal-dialog">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title" id="labelPedirAjuda">Pedir Ajuda</h5>
                                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                            <span aria-hidden="true">&times;</span>
                                        </button>
                                    </div>
                                    <div class="modal-body">
                                        <form id="form_pedir_ajuda" class="user" method="post">
                                            <input type="hidden" name="pedir_ajuda" value="1">
                                            <input type="hidden" name="ocorrencia" value="{{ $order->id }}">

                                            <span>Clique em solicitar ajuda para enviar um e-mail aos responsáveis pelo
                                                setor</span>

                                        </form>


                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary"
                                            data-dismiss="modal">Fechar</button>
                                        <button form="form_pedir_ajuda" type="submit" class="btn btn-primary">Solicitar
                                            Ajuda</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
            <div class="card mb-4">
                <div class="card-body">
                    <b>Requisitante: </b>{{ $order->customer->name }}<br />
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
                    <b>Técnico Responsável: </b>{{ ($order->provider != null ? $order->provider->name : "Não informado") }}<br>
                </div>
            </div>
        </div>
    </div>
</div>
@include('orders.panel-status-logs')