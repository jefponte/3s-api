<?php

/**
 * Classe feita para manipulação do objeto OcorrenciaController
 *
 * @author Jefferson Uchôa Ponte <j.pontee@gmail.com>
 */

namespace app3s\controller;

use App\Enums\OrderStatus;
use App\Models\Division;
use App\Models\Order;
use App\Models\OrderMessage;
use App\Models\OrderStatusLog;
use App\Models\Service;
use App\Models\User;
use app3s\util\Mail;
use app3s\util\Sessao;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class OcorrenciaController
{
    protected $sessao;

    public function main()
    {

        echo '

<div class="card mb-4">
        <div class="card-body">';
        if (isset($_GET['selecionar'])) {
            $this->show();
        } elseif (isset($_GET['cadastrar'])) {
            $this->create();
        } else {
            $this->index();
        }

        echo '
	</div>
</div>
';
    }

    public function index()
    {

        $this->sessao = new Sessao();
        $listaAtrasados = [];
        $lista = [];

        $queryPendding = Order::select(
            'orders.id as id',
            'orders.description as descricao',
            'services.sla as tempo_sla',
            'orders.created_at as data_abertura',
            'orders.status as status'
        )
            ->join('services', 'orders.service_id', '=', 'services.id')
            ->whereIn(
                'status',
                [
                    OrderStatus::opened()->value,
                    OrderStatus::pendingResource()->value,
                    OrderStatus::pendingCustomerResponse()->value,
                    OrderStatus::inProgress()->value,
                    OrderStatus::reopened()->value,
                    OrderStatus::reserved()->value,
                ]
            )->orderByDesc('orders.id');

        $queryFinished = Order::select(
            'orders.id as id',
            'orders.description as descricao',
            'services.sla as tempo_sla',
            'orders.created_at as data_abertura',
            'orders.status as status'
        )
            ->join('services', 'orders.service_id', '=', 'services.id')
            ->whereIn('status', [
                OrderStatus::closed()->value,
                OrderStatus::committed()->value,
                OrderStatus::canceled()->value,
            ])->orderByDesc('orders.id');

        $queryPendding = $this->applyFilters($queryPendding);
        $queryFinished = $this->applyFilters($queryFinished);

        if ($this->sessao->getNivelAcesso() == Sessao::NIVEL_COMUM) {
            $queryPendding = $queryPendding->where('customer_user_id', $this->sessao->getIdUsuario());
            $queryFinished = $queryFinished->where('customer_user_id', $this->sessao->getIdUsuario());
        }
        $lista = $queryPendding->limit(300)->get();
        $lista2 = $queryFinished->limit(300)->get();

        $listaAtrasados = [];
        if ($this->sessao->getNivelAcesso() == Sessao::NIVEL_COMUM) {
            $listNoLate = $lista;
        } else {
            $listNoLate = [];
            foreach ($lista as $ocorrencia) {
                if ($this->atrasado($ocorrencia)) {
                    $listaAtrasados[] = $ocorrencia;
                } else {
                    $listNoLate[] = $ocorrencia;
                }
            }
        }

        //Painel principal
        echo '

		<div class="row">


		<div class="col-md-8 blog-main">
				<div class="panel-group" id="accordion">';

        if (count($listaAtrasados) > 0) {

            echo view(
                'partials.index-orders',
                [
                    'orders' => $listaAtrasados,
                    'id' => 'collapseAtraso',
                    'title' => 'Ocorrências Em Atraso ('.count($listaAtrasados).')',
                    'strShow' => 'show',
                ]
            );
        }
        $this->painel($listNoLate, 'Ocorrências Em Aberto('.count($listNoLate).')', 'collapseAberto', 'show');
        $this->painel($lista2, 'Ocorrências Encerradas', 'collapseEncerrada');
        echo '
			</div>
		</div>
		<aside class="col-md-4 blog-sidebar">';
        if ($this->sessao->getNivelAcesso() == Sessao::NIVEL_ADM || $this->sessao->getNivelAcesso() == Sessao::NIVEL_TECNICO) {
            $userDivision = Division::where('id', request()->user()->division_id)->first();
            $attendents = User::where('role', Sessao::NIVEL_ADM)
                ->orWhere('role', Sessao::NIVEL_TECNICO)->get();
            $allUsers = User::get();
            $applicants = Order::select('place as division_sig', 'division_sig_id as division_sig_id')->distinct()->limit(400)->get();
            $divisions = Division::select('id', 'name')->get();

            echo '
                <div class="p-4 mb-3 bg-light rounded">
                    <h4 class="font-italic">Filtros</h4>';

            echo view('partials.form-basic-filter', ['userDivision' => $userDivision, 'attendents' => $attendents, 'allUsers' => $allUsers]);
            echo view('partials.form-advanced-filter', ['divisions' => $divisions, 'applicants' => $applicants]);
            echo view('partials.form-campus-filter');
            echo '</div>';
        }
        echo view('partials.card-info');
        echo '</aside>
		</div>
		';
    }

    public function show()
    {

        if (! isset($_GET['selecionar'])) {
            return;
        }

        $sessao = new Sessao();
        $selected = Order::findOrFail($_GET['selecionar']);

        if (! $this->parteInteressada($selected)) {
            echo '
            <div class="alert alert-danger" role="alert">
                Você não é cliente deste chamado, nem técnico para atendê-lo.
            </div>
            ';

            return;
        }
        $selected->load([
            'messages.user',
            'messages' => function ($query) {
                $query->orderBy('id', 'asc');
            },
            'statusLogs' => function ($query) {
                $query->orderBy('id', 'asc');
            },
            'division',
            'customer',
            'provider.division',
            'service.division',
        ]);

        $listaUsuarios = DB::table('users')->whereIn('role', [
            Sessao::NIVEL_TECNICO,
            Sessao::NIVEL_ADM,
        ])->get();
        $services = Service::whereIn('role', ['customer', 'provider'])->get();
        $divisions = Division::get();

        $dataSolucao = $this->calcularHoraSolucao($selected->created_at, $selected->service->sla);

        $canEditTag = $this->possoEditarPatrimonio(auth()->user(), $selected);
        $canEditSolution = $this->possoEditarSolucao(auth()->user(), $selected);
        $selected->service_name = $selected->service->name;
        $isClient = ($sessao->getNivelAcesso() == Sessao::NIVEL_COMUM);
        $timeNow = time();
        $timeSolucaoEstimada = strtotime($dataSolucao);
        $isLate = $timeNow > $timeSolucaoEstimada;

        $selected->canEditService = $this->possoEditarServico(auth()->user(), $selected);
        $selected->canCancel = $this->canCancel(auth()->user(), $selected);
        $selected->canRespond = $this->possoAtender(auth()->user(), $selected);
        $selected->canClose = $this->possoFechar(auth()->user(), $selected);
        $selected->canRate = $this->possoAvaliar(auth()->user(), $selected);
        $selected->canReopen = $this->possoReabrir(auth()->user(), $selected);
        $selected->canReserve = $this->possoReservar(auth()->user(), $selected);
        $selected->canRelease = $this->possoLiberar(auth()->user(), $selected);
        $selected->canWait = $this->canWait(auth()->user(), $selected);
        $selected->canSendMessage = $this->possoEnviarMensagem(auth()->user(), $selected);

        foreach ($selected->messages as $mensagemForum) {
            $nome = $mensagemForum->user->name;

            $listaNome = explode(' ', $mensagemForum->user->name);
            if (isset($listaNome[0])) {
                $nome = ucfirst(strtolower($listaNome[0]));
            }
            if (isset($listaNome[1])) {
                if (strlen($listaNome[1]) <= 2) {
                    $nome .= ' '.strtolower($listaNome[1]);
                    if (isset($listaNome[2])) {
                        $nome .= ' '.ucfirst(strtolower($listaNome[2]));
                    }
                } else {
                    $nome .= ' '.ucfirst(strtolower($listaNome[1]));
                }
            }
            $mensagemForum->firstName = $nome;
        }

        $listColorStatus = [
            OrderStatus::opened()->value => '  notice-warning',
            OrderStatus::inProgress()->value => '  notice-info ',
            OrderStatus::closed()->value => 'notice-success ',
            OrderStatus::committed()->value => 'notice-success ',
            OrderStatus::canceled()->value => ' notice-warning ',
            OrderStatus::reopened()->value => '  notice-warning ',
            OrderStatus::reserved()->value => '  notice-warning ',
            OrderStatus::pendingResource()->value => '  notice-warning ',
            OrderStatus::pendingCustomerResponse()->value => ' notice-warning',
        ];

        echo '<div class="row">';
        echo view('partials.modal-form-status', ['services' => $services, 'providers' => $listaUsuarios, 'divisions' => $divisions, 'order' => $selected]);
        echo view('partials.panel-status', ['selected' => $selected]);
        echo view('partials.show-order', [
            'listColorStatus' => $listColorStatus,
            'order' => $selected,
            'canEditTag' => $canEditTag,
            'canEditSolution' => $canEditSolution,
            'isLevelClient' => $isClient,
            'isLate' => $isLate,
            'dataSolucao' => $dataSolucao,
            'providerDivision' => $selected->division->name.' - '.$selected->division->description,

        ]);
        echo view('partials.box-messages', ['order' => $selected]);
    }

    public function calcularHoraSolucao($dataAbertura, $tempoSla)
    {
        $isWeekend = function ($data) {
            $diaDaSemana = intval(date('w', strtotime($data)));

            return $diaDaSemana == 6 || $diaDaSemana == 0;
        };
        $outOfService = function ($data) {
            $hora = intval(date('H', strtotime($data)));

            return ($hora >= 17) || ($hora < 8) || ($hora == 11);
        };
        if ($dataAbertura == null) {
            return 'Indefinido';
        }
        while ($isWeekend($dataAbertura)) {
            $dataAbertura = date('Y-m-d 08:00:00', strtotime('+1 day', strtotime($dataAbertura)));
        }
        while ($outOfService($dataAbertura)) {
            $dataAbertura = date('Y-m-d H:00:00', strtotime('+1 hour', strtotime($dataAbertura)));
        }
        $timeEstimado = strtotime($dataAbertura);
        $tempoSla++;
        for ($i = 0; $i < $tempoSla; $i++) {
            $timeEstimado = strtotime('+'.$i.' hour', strtotime($dataAbertura));
            $horaEstimada = date('Y-m-d H:i:s', $timeEstimado);
            while ($isWeekend($horaEstimada)) {
                $horaEstimada = date('Y-m-d 08:00:00', strtotime('+1 day', strtotime($horaEstimada)));
                $i = $i + 24;
                $tempoSla += 24;
            }

            while ($outOfService($horaEstimada)) {
                $horaEstimada = date('Y-m-d H:i:s', strtotime('+1 hour', strtotime($horaEstimada)));
                $i++;
                $tempoSla++;
            }
        }
        $horaEstimada = date('Y-m-d H:i:s', $timeEstimado);

        return $horaEstimada;
    }
    //Policies em uso

    public function possoEditarServico(User $user, Order $order)
    {
        return $order->provider != null && auth()->user()->id === $order->provider->id
            && $order->status === OrderStatus::inProgress()->value;
    }

    public function canCancel(User $user, Order $order)
    {
        return auth()->user()->id == $order->customer_user_id && $order->status == OrderStatus::opened()->value;
    }

    public function possoAtender(User $user, Order $order)
    {
        return
            (auth()->user()->role === Sessao::NIVEL_ADM
                || auth()->user()->role === Sessao::NIVEL_TECNICO)
            &&
            ($order->status == OrderStatus::opened()->value
                ||
                $order->status == OrderStatus::reopened()->value
                ||
                $order->status == OrderStatus::reserved()->value
                ||
                $order->status == OrderStatus::pendingCustomerResponse()->value
                ||
                $order->status == OrderStatus::pendingResource()->value
            );
    }

    public function possoFechar(User $user, Order $order)
    {
        return $order->provider != null
            && auth()->user()->id == $order->provider->id
            && trim($order->solution) != ''
            && $order->status == OrderStatus::inProgress()->value;
    }

    public function possoAvaliar(User $user, Order $order)
    {
        return $order->status === OrderStatus::closed()->value
            && auth()->user()->id === $order->customer->id
            && $order->customer != null;
    }

    public function possoReabrir(User $user, Order $order)
    {
        return $order->customer != null
            &&
            auth()->user()->id === $order->customer->id
            &&
            $order->status === OrderStatus::closed()->value;
    }

    public function possoReservar(User $user, Order $order)
    {
        return auth()->user()->role === Sessao::NIVEL_ADM
            && ($order->status === OrderStatus::opened()->value
                ||
                $order->status === OrderStatus::reopened()->value
                ||
                $order->status === OrderStatus::inProgress()->value
                ||
                $order->status === OrderStatus::pendingCustomerResponse()->value
                ||
                $order->status === OrderStatus::pendingResource()->value
            );
    }

    public function possoLiberar(User $user, Order $order)
    {
        return auth()->user()->role === Sessao::NIVEL_ADM
            &&
            ($order->status == OrderStatus::inProgress()->value
                ||
                $order->status == OrderStatus::reserved()->value
                ||
                $order->status == OrderStatus::pendingCustomerResponse()->value
                ||
                $order->status == OrderStatus::pendingResource()->value
            );
    }

    public function canWait(User $user, Order $order)
    {
        return $order->provider != null
            && $order->provider->id === auth()->user()->id
            && $order->status === OrderStatus::inProgress()->value;
    }

    public function possoEnviarMensagem(User $user, Order $order)
    {
        return $order->status === OrderStatus::inProgress()->value
            || $order->status === OrderStatus::pendingCustomerResponse()->value
            || $order->status === OrderStatus::pendingResource()->value
            &&
            ($order->provider != null && $order->provider->id === auth()->user()->id
                ||
                $order->customer != null && $order->customer->id === auth()->user()->id
            );
    }

    public function possoCancelar(User $user, Order $order)
    {
        return $user->id === $order->customer->id && ($order->status == OrderStatus::inProgress()->value);
    }

    public function possoEditarSolucao(User $user, Order $order)
    {

        $this->sessao = new Sessao();
        if (
            $order->status === OrderStatus::inProgress()->value
            && $order->provider != null &&
            $this->sessao->getIdUsuario() === $order->provider->id
        ) {
            return true;
        }

        return false;
    }

    public function possoEditarPatrimonio(User $user, Order $order)
    {
        $this->sessao = new Sessao();

        if ($order->status == OrderStatus::closed()->value) {
            return false;
        }

        if ($order->status == OrderStatus::canceled()->value) {
            return false;
        }
        if ($order->status == OrderStatus::committed()->value) {
            return false;
        }
        if ($this->sessao->getIdUsuario() == $order->customer->id) {
            return true;
        }
        if ($order->provider != null && $this->sessao->getIdUsuario() == $order->provider->id) {
            return true;
        }
    }

    public function mudarNivel()
    {
        $sessao = new Sessao();
        if (
            $sessao->getNIvelOriginal() != Sessao::NIVEL_TECNICO
            && $sessao->getNIvelOriginal() != Sessao::NIVEL_ADM
        ) {
            echo ':falha:';

            return;
        }
        if (
            $sessao->getNIvelOriginal() === Sessao::NIVEL_TECNICO
            && $_POST['nivel'] === Sessao::NIVEL_ADM
        ) {
            echo ':falha:';

            return;
        }
        $sessao->setNivelDeAcesso($_POST['nivel']);
        echo ':sucess:'.$sessao->getNivelAcesso();

    }

    public function painel($lista, $strTitulo, $id, $strShow = '')
    {
        echo view(
            'partials.index-orders',
            [
                'orders' => $lista,
                'id' => $id,
                'title' => $strTitulo,
                'strShow' => $strShow,
            ]
        );
    }

    public function atrasado($ocorrencia)
    {
        if ($ocorrencia->tempo_sla < 1) {
            return false;
        }
        $horaEstimada = $this->calcularHoraSolucao($ocorrencia->data_abertura, $ocorrencia->tempo_sla);
        $timeHoje = time();
        $timeSolucaoEstimada = strtotime($horaEstimada);

        return $timeHoje > $timeSolucaoEstimada;
    }

    public function applyFilters($query)
    {
        if (isset($_GET['setor'])) {
            $divisionId = intval($_GET['setor']);
            $query = $query->where('orders.division_id', $divisionId);
        }
        if (isset($_GET['demanda'])) {
            $query = $query->where(function ($query) {
                $query->where('provider_user_id', $this->sessao->getIdUsuario());
            });
        }
        if (isset($_GET['solicitacao'])) {
            $query = $query->where('customer_user_id', $this->sessao->getIdUsuario());
        }
        if (isset($_GET['tecnico'])) {
            $query = $query->where(function ($query) {
                $query->where('provider_user_id', intval($_GET['tecnico']));
            });
        }
        if (isset($_GET['requisitante'])) {
            $query = $query->where('customer_user_id', intval($_GET['requisitante']));
        }
        if (isset($_GET['data_abertura1'])) {
            $data1 = date('Y-m-d', strtotime($_GET['data_abertura1']));
            $query = $query->where('orders.created_at', '>=', $data1);
        }
        if (isset($_GET['data_abertura2'])) {
            $data2 = date('Y-m-d', strtotime($_GET['data_abertura2']));
            $query = $query->where('orders.created_at', '<=', $data2);
        }
        if (isset($_GET['campus'])) {
            $campusArr = explode(',', $_GET['campus']);
            $query = $query->whereIn('campus', $campusArr);
        }
        if (isset($_GET['setores_responsaveis'])) {
            $divisions = explode(',', $_GET['setores_responsaveis']);
            $query = $query->whereIn('orders.division_id', $divisions);
        }
        if (isset($_GET['setores_requisitantes'])) {
            $divisionsSig = explode(',', $_GET['setores_requisitantes']);
            $query = $query->whereIn('division_sig_id', $divisionsSig);
        }

        return $query;
    }

    public function emailNotificationMessage($orderMessage, $order)
    {
        $mail = new Mail();
        $assunto = '[3S] - Chamado Nº '.$order->id;
        $corpo = '<p>Avisamos que houve uma mensagem nova na solicitação <a href="https://3s.unilab.edu.br/?page=ocorrencia&selecionar='.$order->id.'">Nº'.$order->id.'</a></p>';

        $corpo .= '<ul>

                        <li>Corpo: '.$orderMessage->message.'</li>
                        <li>Serviço Solicitado: '.$order->service->name.'</li>
                        <li>Descrição do Problema: '.$order->description.'</li>
                        <li>Setor Responsável: '.$order->division->name.' -
                        '.$order->division->description.'</li>
                        <li>Cliente: '.$order->customer->name.'</li>
                </ul><br><p>Mensagem enviada pelo sistema 3S. Favor não responder.</p>';

        //cutomer
        $saldacao = '<p>Prezado(a) '.$order->customer->name.' ,</p>';
        $mail->enviarEmail($order->email, $order->customer->name, $assunto, $saldacao.$corpo);
        if ($order->provider != null) {
            //Provider
            $saldacao = '<p>Prezado(a) '.$order->provider->name.' ,</p>';
            $mail->enviarEmail($order->provider->email, $order->provider->name, $assunto, $saldacao.$corpo);
        }
    }

    public function addMensagemAjax()
    {

        $sessao = new Sessao();
        if (! isset($_POST['enviar_mensagem_forum'])) {
            return;
        }
        if (! (isset($_POST['tipo'])
            && isset($_POST['mensagem'])
            && isset($_POST['ocorrencia']))) {
            echo ':incompleto';

            return;
        }
        $order = Order::findOrFail(intval($_POST['ocorrencia']));
        $order->load([
            'division',
            'customer',
            'provider.division',
            'service.division',
        ]);
        if ($order->status == 'closed' || $order->status == 'commited') {
            echo ':falha:O chamado já foi fechado.';

            return;
        }
        $messageData = [];
        $messageData['message'] = $_POST['mensagem'];
        $novoNome = '';

        if ($_POST['tipo'] == self::TIPO_TEXTO) {
            $messageData['type'] = self::TIPO_TEXTO;
        } else {

            $messageData['type'] = self::TIPO_ARQUIVO;
            if (request()->hasFile('anexo')) {
                $anexo = request()->file('anexo');
                if (! Storage::exists('public/uploads')) {
                    Storage::makeDirectory('public/uploads');
                }
                $novoNome = $anexo->getClientOriginalName();

                if (Storage::exists('public/uploads/'.$anexo->getClientOriginalName())) {
                    $novoNome = uniqid().'_'.$novoNome;
                }

                $extensaoArr = explode('.', $novoNome);
                $extensao = strtolower(end($extensaoArr));

                $extensoes_permitidas = [
                    'xlsx', 'xlsm', 'xlsb', 'xltx', 'xltm', 'xls', 'xlt', 'xls', 'xml', 'xml', 'xlam', 'xla', 'xlw', 'xlr',
                    'doc', 'docm', 'docx', 'docx', 'dot', 'dotm', 'dotx', 'odt', 'pdf', 'rtf', 'txt', 'wps', 'xml', 'zip', 'rar', 'ovpn',
                    'xml', 'xps', 'jpg', 'gif', 'png', 'pdf', 'jpeg',
                ];

                if (! in_array($extensao, $extensoes_permitidas)) {
                    echo ':falha:Extensão não permitida. Lista de extensões permitidas a seguir. ';
                    echo '('.implode(', ', $extensoes_permitidas).')';

                    return;
                }

                if (! $anexo->storeAs('public/uploads/', $novoNome)) {
                    echo ':falha:arquivo não pode ser enviado';

                    return;
                }
            }

            if ($novoNome === null || $novoNome === '') {
                echo ':falha:Nome do arquivo está nulo';
            }
            $messageData['message'] = $novoNome;
        }
        $messageData['user_id'] = $sessao->getIdUsuario();
        $messageData['order_id'] = $_POST['ocorrencia'];

        $orderMessage = OrderMessage::create($messageData);
        if ($orderMessage && $orderMessage->id) {
            echo ':sucesso:'.$order->id.':';
            $this->emailNotificationMessage($orderMessage, $order);
        } else {
            echo ':falha';
        }
    }

    public function create()
    {
        $this->sessao = new Sessao();
        $listaNaoAvaliados = Order::where('customer_user_id', $this->sessao->getIdUsuario())->where(
            'status',
            OrderStatus::closed()->value
        )->get();

        echo '
            <div class="row">
                <div class="col-md-12 blog-main">';

        $queryService = DB::table('services');
        if ($this->sessao->getNivelAcesso() == Sessao::NIVEL_COMUM) {
            $queryService->where('role', Sessao::NIVEL_COMUM);
        }
        if (
            $this->sessao->getNivelAcesso() == Sessao::NIVEL_ADM
            || $this->sessao->getNivelAcesso() == Sessao::NIVEL_TECNICO
        ) {
            $queryService->whereIn('role', [Sessao::NIVEL_COMUM, Sessao::NIVEL_TECNICO]);
        }
        $services = $queryService->get();

        if (count($listaNaoAvaliados) == 0) {
            echo '<h3 class="pb-4 mb-4 font-italic border-bottom">Cadastrar Ocorrência</h3>';
            echo view('partials.form-insert-order', ['services' => $services, 'email' => $this->sessao->getEmail()]);
        } else {

            echo view(
                'partials.index-orders',
                [
                    'orders' => $listaNaoAvaliados,
                    'title' => 'Para continuar confirme os chamados fechados.',
                    'id' => 'collapseToConfirm',
                    'strShow' => 'show',
                ]
            );
        }
        echo '
                </div>
            </div>';
    }

    public function mainApiMessage()
    {
        $sessao = new Sessao();
        if ($sessao->getNivelAcesso() == Sessao::NIVEL_DESLOGADO) {
            return;
        }
        header('Content-type: application/json');
        $this->get();
    }

    private function parteInteressada($order)
    {
        $sessao = new Sessao();
        if (
            $sessao->getNivelAcesso() == Sessao::NIVEL_TECNICO
            || $sessao->getNivelAcesso() == Sessao::NIVEL_ADM
            || $order->customer->id === $sessao->getIdUsuario()
        ) {
            return true;
        }

        return false;
    }

    private function get()
    {

        if ($_SERVER['REQUEST_METHOD'] != 'GET') {
            return;
        }

        if (! isset($_REQUEST['api'])) {
            return;
        }

        $url = explode('/', $_REQUEST['api']);
        if (count($url) == 0 || $url[0] == '') {
            return;
        }
        if (! isset($url[1])) {
            return;
        }
        if ($url[1] != 'mensagem_forum') {
            return;
        }
        if (! isset($url[2])) {
            return;
        }
        if (isset($url[2]) == '') {
            return;
        }

        $id = intval($url[2]);
        $order = Order::findOrFail($id);
        if (! $this->parteInteressada($order)) {
            echo '{Acesso Negado}';

            return;
        }

        if (isset($url[3]) && $url[3] != '') {
            $idM = intval($url[3]);
            $query = OrderMessage::where('order_id', '=', $order->id)->where('id', '>', $idM)->orderBy('id');
        } else {
            $query = OrderMessage::where('order_id', '=', $order->id)->orderBy('id');
        }

        $list = $query->get();

        if ($list->count() === 0) {
            echo '[]';

            return;
        }

        $listagem = [];
        foreach ($list as $linha) {
            $listagem[] = [
                'id' => $linha->id,
                'tipo' => $linha->type,
                'mensagem' => strip_tags($linha->message),
                'data_envio' => $linha->created_at,
                'nome_usuario' => $linha->user->name,
            ];
        }
        echo json_encode($listagem);
    }

    public function passwordVerify()
    {
        $this->sessao = new Sessao();
        if (! isset($_POST['senha'])) {
            return false;
        }
        $login = $this->sessao->getLoginUsuario();
        $senha = $_POST['senha'];
        $data = ['login' => $login, 'senha' => $senha];
        $response = Http::post(env('UNILAB_API_ORIGIN').'/authenticate', $data);
        $responseJ = json_decode($response->body());

        $idUsuario = 0;

        if (isset($responseJ->id)) {
            $idUsuario = intval($responseJ->id);
        }
        if ($idUsuario === 0) {
            return false;
        }
        if ($responseJ->id != $this->sessao->getIdUsuario()) {
            echo ':falha:Senha Incorreta.';

            return false;
        }

        return true;
    }

    public function mainAjaxStatus()
    {
        if (! isset($_POST['status_acao'])) {
            echo ':falha:Ação não especificada';

            return;
        }
        if (! $this->passwordVerify()) {
            echo ':falha:Senha incorreta';

            return;
        }

        $this->sessao = new Sessao();

        $order = Order::findOrFail($_POST['id_ocorrencia']);
        $order->load([
            'messages.user' => function ($query) {
                $query->orderBy('id', 'asc');
            },
            'statusLogs' => function ($query) {
                $query->orderBy('id', 'asc');
            },
            'division',
            'customer',
            'provider.division',
            'service.division',
        ]);
        $status = false;
        $mensagem = '';
        switch ($_POST['status_acao']) {
            case 'cancelar':
                $status = $this->ajaxCancelar(auth()->user(), $order);
                $mensagem = '<p>Chamado cancelado</p>';
                break;
            case 'atender':
                $status = $this->ajaxAtender(auth()->user(), $order);
                $mensagem = '<p>Chamado em atendimento</p>';
                break;
            case 'editar_solucao':
                $status = $this->ajaxEditarSolucao(auth()->user(), $order);
                $mensagem = '<p>Solução editada</p>';
                break;
            case 'editar_servico':
                $status = $this->ajaxEditarServico(auth()->user(), $order);
                $mensagem = '<p>Serviço alterado</p>';
                break;
            case 'fechar':
                $status = $this->ajaxFechar(auth()->user(), $order);
                $mensagem = '<p>Chamado fechado</p>';
                break;
            case 'reabrir':
                $status = $this->ajaxReabrir(auth()->user(), $order);
                $mensagem = '<p>Chamado reaberto</p>';
                break;
            case 'liberar_atendimento':
                $status = $this->ajaxLiberar(auth()->user(), $order);
                $mensagem = '<p>Chamado Liberado para atendimento</p>';
                break;
            case 'editar_patrimonio':
                $status = $this->ajaxEditarPatrimonio(auth()->user(), $order);
                $mensagem = '<p>Patrimônio editado.</p>';
                break;
            case 'reservar':
                $status = $this->ajaxReservar(auth()->user(), $order);
                $mensagem = '<p>Chamado reservado</p>';
                break;
            case 'avaliar':
                $status = $this->ajaxAvaliar(auth()->user(), $order);
                $mensagem = '<p>Chamado avaliado</p>';
                break;
            case 'aguardar_ativos':
                $status = $this->ajaxAguardandoAtivo(auth()->user(), $order);
                $mensagem = '<p>Aguardando ativo de TI</p>';
                break;
            case 'aguardar_usuario':
                $status = $this->ajaxAguardandoUsuario(auth()->user(), $order);
                $mensagem = '<p>Aguardando resposta do cliente</p>';
                break;

            default:
                echo ':falha:Ação não encontrada';

                break;
        }
        if ($status) {
            $this->enviarEmail($order, $mensagem);
        }
    }

    public function ajaxAtender(User $user, $order)
    {
        if (! $this->possoAtender($user, $order)) {
            echo ':falha:Você não pode fazer esta alteração.';

            return false;
        }
        $newStatus = OrderStatus::inProgress()->value;
        $messageStatus = 'Chamado em atendimento';
        $order->status = $newStatus;
        $order->division_id = $user->division_id;
        $order->provider_user_id = $user->id;
        $order->service_at = now();

        $newLog = [
            'order_id' => $order->id,
            'status' => $newStatus,
            'message' => $messageStatus,
            'user_id' => $user->id,
        ];

        DB::beginTransaction();
        try {
            OrderStatusLog::create($newLog);
            $order->save();
            DB::commit();
            echo ':sucesso:'.$order->id.':Atendimento alterado com sucesso!';
        } catch (\Exception $e) {
            DB::rollback();
            echo ':falha:Falha ao tentar inserir histórico.';
        }
    }

    public function ajaxCancelar(User $user, $order)
    {
        $this->sessao = new Sessao();
        if (! $this->possoCancelar(auth()->user(), $order)) {
            echo ':falha:Você não pode fazer esta alteração.';

            return false;
        }
        $newStatus = OrderStatus::canceled()->value;
        $order->status = $newStatus;
        $messageStatus = 'Ocorrência cancelada pelo usuário';
        $newLog = [
            'order_id' => $order->id,
            'status' => $newStatus,
            'message' => $messageStatus,
            'user_id' => $user->id,
        ];

        DB::beginTransaction();
        try {
            OrderStatusLog::create($newLog);
            $order->save();
            DB::commit();
            echo ':sucesso:'.$order->id.':Atendimento alterado com sucesso!';
        } catch (\Exception $e) {
            DB::rollback();
            echo ':falha:Falha ao tentar inserir histórico.';
        }

        return true;
    }

    public function ajaxFechar(User $user, $order)
    {
        if (! $this->possoFechar(auth()->user(), $order)) {
            echo ':falha:Você não pode fazer esta alteração.';

            return false;
        }
        $newStatus = OrderStatus::closed()->value;
        $messageStatus = 'Ocorrência fechada pelo atendente';
        $order->status = $newStatus;
        $order->finished_at = now();

        $newLog = [
            'order_id' => $order->id,
            'status' => $newStatus,
            'message' => $messageStatus,
            'user_id' => $user->id,
        ];

        DB::beginTransaction();
        try {
            OrderStatusLog::create($newLog);
            $order->save();
            DB::commit();
            echo ':sucesso:'.$order->id.':Atendimento alterado com sucesso!';
        } catch (\Exception $e) {
            DB::rollback();
            echo ':falha:Falha ao tentar inserir histórico.';
        }

        return true;
    }

    public function ajaxReabrir(User $user, $order)
    {

        $this->sessao = new Sessao();
        if (! $this->possoReabrir($user, $order)) {
            echo ':falha:Você não pode fazer esta alteração.';

            return false;
        }

        DB::beginTransaction();
        try {
            OrderStatusLog::create([
                'order_id' => $order->id,
                'status' => OrderStatus::reopened()->value,
                'message' => 'Ocorrência Reaberta pelo cliente',
                'user_id' => $user->id,
            ]);
            $order->status = OrderStatus::reopened()->value;
            $order->save();
            DB::commit();
            echo ':sucesso:'.$order->id.':Atendimento alterado com sucesso!';
        } catch (\Exception $e) {
            DB::rollback();
            echo ':falha:Falha ao tentar inserir histórico.';
        }

        return true;
    }

    public function ajaxEditarPatrimonio(User $user, $order)
    {
        $this->sessao = new Sessao();
        if (! $this->possoEditarPatrimonio(auth()->user(), $order)) {
            echo ':falha:Você não pode fazer esta alteração.';

            return false;
        }
        if (! isset($_POST['patrimonio'])) {
            echo ':falha:Digite um patrimônio.';

            return false;
        }

        DB::beginTransaction();
        try {
            OrderStatusLog::create([
                'order_id' => $order->id,
                'status' => OrderStatus::opened()->value,
                'message' => 'Técnico editou o Patrimônio para: '.$_POST['patrimonio'],
                'user_id' => auth()->user()->id,
            ]);
            $order->status = OrderStatus::opened()->value;
            $order->save();
            DB::commit();
            echo ':sucesso:'.$order->id.':Atendimento alterado com sucesso!';
        } catch (\Exception $e) {
            DB::rollback();
            echo ':falha:Falha ao tentar inserir histórico.';
        }

        return true;
    }

    public function ajaxReservar(User $user, $order)
    {

        $this->sessao = new Sessao();
        if (! $this->possoReservar($user, $order)) {
            echo ':falha:Você não pode fazer esta alteração.';

            return false;
        }
        if (! isset($_POST['tecnico'])) {
            echo ':falha:Técnico especificado';

            return false;
        }
        $provider = User::findOrFail($_POST['tecnico']);

        $order->status = OrderStatus::reserved()->value;
        $order->provider_user_id = $provider->id;

        DB::beginTransaction();
        try {
            OrderStatusLog::create([
                'order_id' => $order->id,
                'status' => OrderStatus::reserved()->value,
                'message' => 'Atendimento reservado para '.$provider->name,
                'user_id' => $user->id,
            ]);

            $order->save();
            DB::commit();
            echo ':sucesso:'.$order->id.':Atendimento alterado com sucesso!';
        } catch (\Exception $e) {
            DB::rollback();
            echo ':falha:Falha ao tentar inserir histórico.';
        }

        return true;
    }

    public function ajaxAvaliar(User $user, $order)
    {

        $this->sessao = new Sessao();
        if (! $this->possoAvaliar($user, $order)) {
            echo ':falha:Você não pode fazer esta alteração.';

            return false;
        }
        if (! isset($_POST['avaliacao'])) {
            echo ':falha:Faça uma avaliação';

            return false;
        }
        DB::beginTransaction();
        try {
            OrderStatusLog::create([
                'order_id' => $order->id,
                'status' => OrderStatus::committed()->value,
                'message' => 'Atendimento avaliado pelo cliente',
                'user_id' => $user->id,
            ]);
            $order->status = OrderStatus::committed()->value;
            $order->rating = $_POST['avaliacao'];
            $order->committed_at = now();
            $order->save();
            DB::commit();
            echo ':sucesso:'.$order->id.':Atendimento alterado com sucesso!';
        } catch (\Exception $e) {
            DB::rollback();
            echo ':falha:Falha ao tentar inserir histórico.';
        }

        return true;
    }

    public function ajaxAguardandoAtivo(User $user, $order)
    {

        $this->sessao = new Sessao();
        if (! $this->canWait($user, $order)) {
            echo ':falha:Você não pode fazer esta alteração.';

            return false;
        }

        DB::beginTransaction();
        try {
            OrderStatusLog::create([
                'order_id' => $order->id,
                'status' => OrderStatus::pendingResource()->value,
                'message' => 'Aguardando ativo',
                'user_id' => $user->id,
            ]);
            $order->status = OrderStatus::pendingResource()->value;
            $order->save();
            DB::commit();
            echo ':sucesso:'.$order->id.':Atendimento alterado com sucesso!';
        } catch (\Exception $e) {
            DB::rollback();
            echo ':falha:Falha ao tentar inserir histórico.';
        }

        return true;
    }

    public function ajaxAguardandoUsuario(User $user, $order)
    {

        $this->sessao = new Sessao();
        if (! $this->canWait($user, $order)) {
            echo ':falha:Você não pode fazer esta alteração.';

            return false;
        }

        DB::beginTransaction();
        try {
            OrderStatusLog::create([
                'order_id' => $order->id,
                'status' => OrderStatus::pendingCustomerResponse()->value,
                'message' => 'Aguardando ativo',
                'user_id' => $user->id,
            ]);
            $order->status = OrderStatus::pendingCustomerResponse()->value;
            $order->save();
            DB::commit();
            echo ':sucesso:'.$order->id.':Atendimento alterado com sucesso!';
        } catch (\Exception $e) {
            DB::rollback();
            echo ':falha:Falha ao tentar inserir histórico.';
        }

        return true;
    }

    public function ajaxEditarSolucao(User $user, $order)
    {

        $this->sessao = new Sessao();
        if (! $this->possoEditarSolucao(auth()->user(), $order)) {
            echo ':falha:Esta solução não pode ser editada.';

            return false;
        }
        if (trim($_POST['solucao']) == '') {
            echo ':falha:Digite uma solução.';

            return false;
        }

        DB::beginTransaction();
        try {
            OrderStatusLog::create([
                'order_id' => $order->id,
                'status' => OrderStatus::inProgress()->value,
                'message' => 'Técnico editou a solução.',
                'user_id' => $user->id,
            ]);
            $order->status = OrderStatus::inProgress()->value;
            $order->solution = trim($_POST['solucao']);
            $order->save();
            DB::commit();
            echo ':sucesso:'.$order->id.':Solução editada com sucesso!';
        } catch (\Exception $e) {
            DB::rollback();
            echo ':falha:Falha ao tentar inserir histórico.';

            return false;
        }

        return true;
    }

    public function ajaxEditarServico(User $user, $order)
    {

        $this->sessao = new Sessao();
        if (! $this->possoEditarServico(auth()->user(), $order)) {
            echo ':falha:Este serviço não pode ser editado.';

            return false;
        }
        if (! isset($_POST['id_servico'])) {
            echo ':falha:Selecione um serviço.';

            return false;
        }
        $service = Service::findOrFail(intval($_POST['id_servico']));
        $service->load([
            'division',
        ]);

        DB::beginTransaction();
        try {
            OrderStatusLog::create([
                'order_id' => $order->id,
                'status' => OrderStatus::opened()->value,
                'message' => 'Técnico alterou o serviço. ',
                'user_id' => $user->id,
            ]);
            $order->status = OrderStatus::opened()->value;
            $order->service_id = $service->id;
            $order->division_id = $service->division->id;
            $order->save();
            DB::commit();
            echo ':sucesso:'.$order->id.':Serviço editado com sucesso!';
        } catch (\Exception $e) {
            DB::rollback();
            echo ':falha:Falha ao editar serviço.';

            return false;
        }

        return true;
    }

    public function ajaxLiberar(User $user, $order)
    {
        $this->sessao = new Sessao();
        if (! $this->possoLiberar($user, $order)) {
            echo ':falha:Não é possível liberar esse atendimento.';

            return false;
        }
        $order->status = OrderStatus::opened()->value;
        DB::beginTransaction();
        try {
            OrderStatusLog::create([
                'order_id' => $order->id,
                'status' => OrderStatus::opened()->value,
                'message' => 'Liberado para atendimento. ',
                'user_id' => $user->id,
            ]);
            $order->save();
            DB::commit();
            echo ':sucesso:'.$order->id.':Liberado para atendimento com sucesso!';
        } catch (\Exception $e) {
            DB::rollback();
            echo ':falha:Falha ao editar serviço.';

            return false;
        }

        return true;
    }

    public function enviarEmail($order, $mensagem = '')
    {
        $mail = new Mail();
        $assunto = '[3S] - Chamado Nº '.$order->id;

        $saldacao = '<p>Prezado(a),</p>';

        $corpo = '<p>Avisamos que houve uma mudança no status da solicitação
		<a href="https://3s.unilab.edu.br/?page=ocorrencia&selecionar='.
            $order->id.'">Nº'.$order->id.'</a></p>';
        $corpo .= $mensagem;
        $corpo .= '<ul>
                        <li>Serviço Solicitado: '.$order->service->name.'</li>
                        <li>Descrição do Problema: '.$order->description.'</li>
                        <li>Setor Responsável: '.$order->division->name.' -
                        '.$order->division->description.'</li>
                        <li>Cliente: '.$order->customer->name.'</li>
                </ul><br><p>Mensagem enviada pelo sistema 3S. Favor não responder.</p>';

        $destinatario = $order->email;
        $nome = $order->customer->name;
        //Cliente do chamado
        $mail->enviarEmail(auth()->user()->email, auth()->user()->name, $assunto, $saldacao.$corpo);

        //Area responsável
        $destinatario = $order->division->email;
        $nome = $order->division->name;
        $mail->enviarEmail($destinatario, $nome, $assunto, $saldacao.$corpo); //Email para area responsavel
        if ($order->provider != null) {
            //O atendente;
            $destinatario = $order->provider->email;
            $nome = $order->provider->name;
            $mail->enviarEmail($destinatario, $nome, $assunto, $saldacao.$corpo);
        }
    }

    const TIPO_ARQUIVO = 'file';

    const TIPO_TEXTO = 'text';
}
