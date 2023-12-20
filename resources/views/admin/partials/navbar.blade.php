<nav class="navbar navbar-expand-lg navbar-light bg-light">

    <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarSupportedContent"
        aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="Toggle navigation">
        <span class="navbar-toggler-icon"></span>
    </button>

    <div class="collapse navbar-collapse" id="navbarSupportedContent">
        <ul class="navbar-nav mr-auto">
            <li class="nav-item active">
                <a class="nav-link" href=".">Início<span class="sr-only">(current)</span></a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="?page=ocorrencia&cadastrar=1">Abrir Chamado</a>
            </li>


            <li class="nav-item dropdown">
                <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button"
                    data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                    Paineis
                </a>
                <div class="dropdown-menu" aria-labelledby="navbarDropdown">
                    <a class="dropdown-item" href="{{route('kamban')}}">Kanban</a>
                    <a class="dropdown-item" href="{{route('kamban')}}">Tabela</a>
                </div>
            </li>


            <li class="nav-item dropdown">
                <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button"
                    data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                    Gerenciamento
                </a>
                <div class="dropdown-menu" aria-labelledby="navbarDropdown">
                    <a class="dropdown-item" href="{{route('kamban')}}">Serviços</a>
                    <div class="dropdown-divider"></div>
                    <a class="dropdown-item" href="{{route('kamban')}}">Unidades</a>
                    <a class="dropdown-item" href="{{route('kamban')}}">Usuários</a>
                </div>
            </li>
        </ul>

        <form action="" method="get">
            @csrf
            <div class="input-group">
                <input type="hidden" name="page" value="ocorrencia">
                <input type="text" name="selecionar" class="form-control" placeholder="Número do chamado"
                    aria-label="Número do Chamado" aria-describedby="button-addon2">
                <div class="input-group-append">
                    <button class="btn btn-outline-secondary" type="form" id="button-addon2">
                        <i class="fa fa-search"></i>
                    </button>
                </div>
            </div>
        </form>

        <div class="btn-group">
            <button class="btn btn-light dropdown-toggle" type="button" data-toggle="dropdown" aria-haspopup="true"
                aria-expanded="false">
                <i class="fa fa-user"></i> Olá, {{ $userFirstName }}
            </button>
            <div class="dropdown-menu dropright">
                <button type="button" disabled class="dropdown-item">
                    Setor: {{ $divisionSig }}
                </button>
                <hr>
                <button type="button" disabled class="dropdown-item change-level">
                    Perfil Admin
                </button>
                <button type="button" nivel="provider" id="change-to-tec" ' . $disabled . '
                    class="dropdown-item change-level">
                    Perfil Técnico
                </button>

                <button type="button" nivel="customer" id="change-to-default"
                    class="dropdown-item change-level">
                    Perfil Cliente
                </button>
                <hr>
                <a href="?sair=1" id="botao-avaliar" acao="avaliar" class="dropdown-item">
                    Sair
                </a>
            </div>
        </div>

    </div>
</nav>
