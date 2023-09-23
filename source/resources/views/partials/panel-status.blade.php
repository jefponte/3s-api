<div class="col-md-12 blog-main">
    <div class="row">
        <div class="col-xl-12 col-lg-12 col-md-12 col-sm-12">

            <div class="col-xl-12 col-lg-12 col-md-12 col-sm-12">
                <div class="alert  bg-light d-flex justify-content-between align-items-center" role="alert">
                    <div class="btn-group">
                        <button class="btn btn-light btn-lg dropdown-toggle p-2" type="button" data-toggle="dropdown"
                            aria-haspopup="true" aria-expanded="false">
                            Chamado {{ $selected->id }}
                        </button>
                        <div class="dropdown-menu">
                            <button type="button" acao="cancelar" {{ $selected->canCancel ? '' : 'disabled' }}
                                class="dropdown-item  botao-status" data-toggle="modal" data-target="#modalStatus">
                                Cancelar
                            </button>
                            <button type="button" {{ $selected->canClose ? '' : 'disabled' }} acao="fechar"
                                class="dropdown-item  botao-status" data-toggle="modal" data-target="#modalStatus">
                                Fechar
                            </button>
                            <button type="button" {{ $selected->canRate ? '' : 'disabled' }} id="avaliar-btn"
                                acao="avaliar" class="dropdown-item" data-toggle="modal" data-target="#modalStatus">
                                Confirmar
                            </button>
                            <button id="botao-reabrir" type="button" {{ $selected->canReopen ? '' : 'disabled' }}
                                acao="reabrir" class="dropdown-item" data-toggle="modal" data-target="#modalStatus">
                                Reabrir
                            </button>
                            @if (auth()->user()->role === 'administrator')
                                <button type="button" acao="reservar" {{ $selected->canReserve ? '' : 'disabled' }}
                                    id="botao-reservar" class="dropdown-item" data-toggle="modal"
                                    data-target="#modalStatus">
                                    Reservar
                                </button>
                            @endif

                            @if (auth()->user()->role === 'administrator' || auth()->user()->role === 'provider')
                                <button type="button" {{ $selected->canRespond ? '' : 'disabled' }} acao="atender"
                                    class="dropdown-item  botao-status" data-toggle="modal" data-target="#modalStatus">
                                    Atender
                                </button>
                                <button type="button" acao="liberar_atendimento"
                                    {{ $selected->canRelease ? '' : 'disabled' }} class="dropdown-item  botao-status"
                                    data-toggle="modal" data-target="#modalStatus">
                                    Liberar Ocorrência
                                </button>
                                <div class="dropdown-divider"></div>
                                <button type="button" acao="aguardar_usuario" class="dropdown-item  botao-status"
                                    {{ $selected->canWait ? '' : 'disabled' }}
                                    data-toggle="modal" data-target="#modalStatus">
                                    Aguardar Usuário
                                </button>
                                <button type="button" acao="aguardar_ativos" class="dropdown-item  botao-status"
                                    {{ $selected->canWait ? '' : 'disabled' }}
                                    data-toggle="modal" data-target="#modalStatus">
                                    Aguardar Ativos
                                </button>
                            @endif

                        </div>
                    </div>
                    <button class="btn btn-light btn-lg p-2" type="button" disabled>
                        Status: {{ $selected->status }}
                    </button>
                </div>
            </div>
        </div>
    </div>
    <div class="row  border-bottom mb-3"></div>
</div>
