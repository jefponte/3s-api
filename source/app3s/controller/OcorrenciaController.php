<?php

/**
 * Classe feita para manipulação do objeto OcorrenciaController
 * @author Jefferson Uchôa Ponte <j.pontee@gmail.com>
 */

namespace app3s\controller;

use app3s\dao\OcorrenciaDAO;
use app3s\dao\ServicoDAO;
use app3s\dao\StatusOcorrenciaDAO;
use app3s\model\Ocorrencia;
use app3s\model\StatusOcorrencia;
use app3s\util\Mail;
use app3s\util\Sessao;
use App\Models\Division;
use App\Models\Order;
use App\Models\Service;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class OcorrenciaController
{

	protected $selecionado;
	protected $sessao;
	protected $dao;

	public function __construct()
	{
		$this->dao = new OcorrenciaDAO();
	}
	public function main()
	{

		echo '

<div class="card mb-4">
        <div class="card-body">';
		if (isset($_GET['selecionar'])) {
			$this->show();
		} else if (isset($_GET['cadastrar'])) {
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
		$listaAtrasados = array();
		$lista = array();

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
					self::STATUS_ABERTO,
					self::STATUS_AGUARDANDO_ATIVO,
					self::STATUS_AGUARDANDO_USUARIO,
					self::STATUS_ATENDIMENTO,
					self::STATUS_REABERTO,
					self::STATUS_RESERVADO
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
				self::STATUS_FECHADO,
				self::STATUS_FECHADO_CONFIRMADO,
				self::STATUS_CANCELADO
			])->orderByDesc('orders.id');

		$queryPendding = $this->applyFilters($queryPendding);
		$queryFinished = $this->applyFilters($queryFinished);

		if ($this->sessao->getNivelAcesso() == Sessao::NIVEL_COMUM) {
			$queryPendding = $queryPendding->where('customer_user_id', $this->sessao->getIdUsuario());
			$queryFinished = $queryFinished->where('customer_user_id', $this->sessao->getIdUsuario());
		}
		$lista = $queryPendding->limit(300)->get();
		$lista2 = $queryFinished->limit(300)->get();


		$listaAtrasados = array();
		if ($this->sessao->getNivelAcesso() == Sessao::NIVEL_COMUM) {
			$listNoLate = $lista;
		} else {
			$listNoLate = array();
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
					'title' => 'Ocorrências Em Atraso (' . count($listaAtrasados) . ')',
					'strShow' => "show"
				]
			);
		}
		$this->painel($listNoLate, 'Ocorrências Em Aberto(' . count($listNoLate) . ')', 'collapseAberto', 'show');
		$this->painel($lista2, "Ocorrências Encerradas", 'collapseEncerrada');
		echo '
			</div>
		</div>';

		//Painel Lateral
		echo '
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


	public function fimDeSemana($data)
	{
		$diaDaSemana = intval(date('w', strtotime($data)));
		return ($diaDaSemana == 6 || $diaDaSemana == 0);
	}

	public function foraDoExpediente($data)
	{
		$hora = date('H', strtotime($data));
		$hora = intval($hora);
		if ($hora >= 17) {
			return true;
		}
		if ($hora < 8) {
			return true;
		}
		if ($hora == 11) {
			return true;
		}
		return false;
	}
	public function calcularHoraSolucao($dataAbertura, $tempoSla)
	{
		if ($dataAbertura == null) {
			return "Indefinido";
		}
		while ($this->fimDeSemana($dataAbertura)) {
			$dataAbertura = date("Y-m-d 08:00:00", strtotime('+1 day', strtotime($dataAbertura)));
		}
		while ($this->foraDoExpediente($dataAbertura)) {
			$dataAbertura = date("Y-m-d H:00:00", strtotime('+1 hour', strtotime($dataAbertura)));
		}
		$timeEstimado = strtotime($dataAbertura);
		$tempoSla++;
		for ($i = 0; $i < $tempoSla; $i++) {
			$timeEstimado = strtotime('+' . $i . ' hour', strtotime($dataAbertura));
			$horaEstimada = date("Y-m-d H:i:s", $timeEstimado);
			while ($this->fimDeSemana($horaEstimada)) {
				$horaEstimada = date("Y-m-d 08:00:00", strtotime('+1 day', strtotime($horaEstimada)));
				$i = $i + 24;
				$tempoSla += 24;
			}

			while ($this->foraDoExpediente($horaEstimada)) {
				$horaEstimada = date("Y-m-d H:i:s", strtotime('+1 hour', strtotime($horaEstimada)));
				$i++;
				$tempoSla++;
			}
		}
		$horaEstimada = date('Y-m-d H:i:s', $timeEstimado);
		return $horaEstimada;
	}


	public function parteInteressada()
	{
		if ($this->sessao->getNivelAcesso() == Sessao::NIVEL_TECNICO) {
			return true;
		} else if ($this->sessao->getNivelAcesso() == Sessao::NIVEL_ADM) {
			return true;
		} else if ($this->selecionado->getUsuarioCliente()->getId() == $this->sessao->getIdUsuario()) {
			return true;
		} else {
			return false;
		}
	}


	public function getColorStatus($siglaStatus)
	{
		$strCartao = ' alert-warning ';
		if ($siglaStatus == self::STATUS_ABERTO) {
			$strCartao = '  notice-warning';
		} else if ($siglaStatus == self::STATUS_ATENDIMENTO) {
			$strCartao = '  notice-info ';
		} else if ($siglaStatus == self::STATUS_FECHADO) {
			$strCartao = 'notice-success ';
		} else if ($siglaStatus == self::STATUS_FECHADO_CONFIRMADO) {
			$strCartao = 'notice-success ';
		} else if ($siglaStatus == self::STATUS_CANCELADO) {
			$strCartao = ' notice-warning ';
		} else if ($siglaStatus == self::STATUS_REABERTO) {
			$strCartao = '  notice-warning ';
		} else if ($siglaStatus == self::STATUS_RESERVADO) {
			$strCartao = '  notice-warning ';
		} else if ($siglaStatus == self::STATUS_AGUARDANDO_USUARIO) {
			$strCartao = '  notice-warning ';
		} else if ($siglaStatus == self::STATUS_AGUARDANDO_ATIVO) {
			$strCartao = ' notice-warning';
		}
		return $strCartao;
	}
	public function canCancel($order)
	{
		return $this->sessao->getIdUsuario() == $order->customer_user_id && $order->status == self::STATUS_ABERTO;
	}
	public function canWait($currentStatus)
	{
		return $this->sessao->getNivelAcesso() != Sessao::NIVEL_COMUM && $currentStatus == 'e' && $this->sessao->getIdUsuario() != $this->selecionado->getIdUsuarioAtendente();
	}
	public function show()
	{

		if (!isset($_GET['selecionar'])) {
			return;
		}

		$sessao = new Sessao();
		$this->sessao = new Sessao();

		if (!$this->parteInteressada()) {
			echo '
            <div class="alert alert-danger" role="alert">
                Você não é cliente deste chamado, nem técnico para atendê-lo.
            </div>
            ';
			return;
		}

		$selected = Order::findOrFail($_GET['selecionar']);
		$selected->load([
			'messages.user' => function ($query) {
				$query->orderBy('id', 'asc');
			},
			'statusLogs' => function ($query) {
				$query->orderBy('id', 'asc');
			},
			'division',
			'customer',
			'provider.division',
			'service.division'
		]);




		$listaUsuarios = DB::table('users')->whereIn('role', [
			Sessao::NIVEL_TECNICO,
			Sessao::NIVEL_ADM
		])->get();
		$services = Service::whereIn('role', ['customer', 'provider'])->get();
		$divisions = Division::get();

		$dataSolucao = $this->calcularHoraSolucao($selected->created_at, $selected->service->sla);

		$canEditTag = $this->possoEditarPatrimonio($selected);
		$canEditSolution = $this->possoEditarSolucao($selected);
		$selected->service_name = $selected->service->name;
		$canEditService = $this->possoEditarServico($selected);
		$isClient = ($sessao->getNivelAcesso() == Sessao::NIVEL_COMUM);
		$timeNow = time();
		$timeSolucaoEstimada = strtotime($dataSolucao);
		$isLate = $timeNow > $timeSolucaoEstimada;


		$providerName = '';
		if ($selected->provider != null) {
			$providerName = $selected->provider->name;
		}
		foreach ($selected->statusLogs as $status) {
			$status->color = $this->getColorStatus($status->status);
		}

		echo view('partials.modal-form-status', ['services' => $services, 'providers' => $listaUsuarios, 'divisions' => $divisions, 'order' => $selected]);

		$this->painelStatus($selected);


		echo view('partials.show-order', [
			'order' => $selected,
			'canEditTag' => $canEditTag,
			'canEditSolution' => $canEditSolution,
			'canEditService' => $canEditService,
			'isLevelClient' => $isClient,
			'isLate' => $isLate,
			'dataSolucao' => $dataSolucao,
			'providerDivision' => $selected->division->name . ' - ' . $selected->division->description,
			'providerName' => $providerName
		]);
		$mensagemController = new MensagemForumController();
		$mensagemController->mainOcorrencia($selected);
	}


	public function painelStatus($selected)
	{

		$this->sessao = new Sessao();
		$canCancel = $this->canCancel($selected);
		echo '
            <div class="row">
                <div class="col-md-12 blog-main">
					<div class="row">
                		<div class="col-xl-12 col-lg-12 col-md-12 col-sm-12">

						<div class="col-xl-12 col-lg-12 col-md-12 col-sm-12">
			<div class="alert  bg-light d-flex justify-content-between align-items-center" role="alert">
				<div class="btn-group">
					<button class="btn btn-light btn-lg dropdown-toggle p-2" type="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
					Chamado ' . $selected->id . '
					</button>
					<div class="dropdown-menu">

					<button type="button" acao="cancelar" ' . ($canCancel ? '' : 'disabled') . ' class="dropdown-item  botao-status"  data-toggle="modal" data-target="#modalStatus">
						Cancelar
						</button>

						';

		if ($this->sessao->getNivelAcesso() == Sessao::NIVEL_ADM || $this->sessao->getNivelAcesso() == Sessao::NIVEL_TECNICO) {
			echo '
			<button type="button"  ' . ($this->possoAtender($selected) ? '' : 'disabled') . '  acao="atender" class="dropdown-item  botao-status"  data-toggle="modal" data-target="#modalStatus">
			  Atender
			</button>

			';
		}

		echo '

		<button type="button" ' . ($this->possoFechar($selected) ? '' : 'disabled') . '  acao="fechar"  class="dropdown-item  botao-status"  data-toggle="modal" data-target="#modalStatus">
  			Fechar
		</button>
		<button type="button" ' . ($this->possoAvaliar($selected) ? '' : 'disabled') . '  id="avaliar-btn" acao="avaliar"  class="dropdown-item"  data-toggle="modal" data-target="#modalStatus">
			Confirmar
	  	</button>

		  <button id="botao-reabrir" type="button" ' . ($this->possoReabrir($selected) ? '' : 'disabled') . '  acao="reabrir"  class="dropdown-item"  data-toggle="modal" data-target="#modalStatus">
		  Reabrir
		</button>

		';

		if ($this->possoReservar($selected)) {
			echo '<button type="button" acao="reservar" id="botao-reservar" class="dropdown-item"  data-toggle="modal" data-target="#modalStatus">
			Reservar
		  </button>';
		}

		if ($this->possoLiberar($selected)) {
			echo '<button type="button" acao="liberar_atendimento"  class="dropdown-item  botao-status"  data-toggle="modal" data-target="#modalStatus">
			Liberar Ocorrência
		  </button>';
		}
		echo '<div class="dropdown-divider"></div>

		<button type="button" acao="aguardar_usuario"  class="dropdown-item  botao-status"  data-toggle="modal" data-target="#modalStatus">
			Aguardar Usuário
		  </button>
		  <button type="button" acao="aguardar_ativos"  class="dropdown-item  botao-status"  data-toggle="modal" data-target="#modalStatus">
			  Aguardar Ativos de TI
		</button>
		</div>
		</div>
		<button class="btn btn-light btn-lg p-2" type="button" disabled>
		Status:  ' . $selected->status . '
		</button>
		</div>
		</div>
		</div>
		</div>
		<div class="row  border-bottom mb-3"></div>
		</div>';
	}

	public function possoCancelar($order)
	{
		return $this->sessao->getIdUsuario() === $order->customer->id
			&&
			($order->status == self::STATUS_REABERTO || $order->status == self::STATUS_ABERTO);
	}
	public function possoLiberar($order)
	{
		if ($this->sessao->getNivelAcesso() === Sessao::NIVEL_ADM
			&&
			(
				$order->status == self::STATUS_ATENDIMENTO
				||
				$order->status == self::STATUS_RESERVADO
				||
				$order->status == self::STATUS_AGUARDANDO_ATIVO
				||
				$order->status == self::STATUS_AGUARDANDO_USUARIO
			)
		) {
			return true;
		}
		return false;
	}

	public function possoAtender($order)
	{
		if (
			$this->sessao->getNivelAcesso() == Sessao::NIVEL_DESLOGADO
			|| $this->sessao->getNivelAcesso() == Sessao::NIVEL_COMUM
		) {
			return false;
		}
		if ($order->status == self::STATUS_ATENDIMENTO) {
			return false;
		}
		if ($order->status == self::STATUS_CANCELADO) {
			return false;
		}
		if ($order->status == self::STATUS_FECHADO || $order->status == self::STATUS_FECHADO_CONFIRMADO) {
			return false;
		}
		if ($order->status == self::STATUS_RESERVADO) {
			if ($order->provider != null & $this->sessao->getIdUsuario() != $order->provider->id) {
				return false;
			}
		}
		if (
			$order->status == self::STATUS_AGUARDANDO_ATIVO
			|| $order->status == self::STATUS_AGUARDANDO_USUARIO
		) {
			if ($order->provider != null && $this->sessao->getIdUsuario() != $order->provider->id) {
				return false;
			}
		}
		if ($order->status == self::STATUS_ABERTO || $order->status == self::STATUS_REABERTO) {
			return true;
		}

		return true;
	}

	public function possoFechar($order)
	{
		if (trim($order->solution) == "") {
			return false;
		}
		if ($this->sessao->getNivelAcesso() == Sessao::NIVEL_COMUM) {
			return false;
		}
		if ($this->sessao->getNivelAcesso() == Sessao::NIVEL_DESLOGADO) {
			return false;
		}

		if ($order->status == Self::STATUS_ATENDIMENTO) {
			if ($this->sessao->getIdUsuario() == $order->provider->id) {
				return true;
			}
		}

		return false;
	}


	public function possoEditarServico($order)
	{
		$this->sessao = new Sessao();
		if (
			$order->provider != null && $this->sessao->getIdUsuario() === $order->provider->id
			&& $order->status != self::STATUS_ATENDIMENTO
			) {
			return true;
		}
		return false;
	}
	public function possoEditarAreaResponsavel($order)
	{

		$this->sessao = new Sessao();
		if ($this->sessao->getNivelAcesso() != Sessao::NIVEL_ADM) {
			return false;
		}

		if ($order->status == self::STATUS_ABERTO) {
			return true;
		}
		if ($order->status == self::STATUS_REABERTO) {
			return true;
		}
		return false;
	}


	public function possoEditarSolucao($order)
	{
		$this->sessao = new Sessao();
		if ($this->sessao->getNivelAcesso() == Sessao::NIVEL_COMUM || $this->sessao->getNivelAcesso() == Sessao::NIVEL_DESLOGADO) {
			return false;
		}
		if (
			$order->status === self::STATUS_ATENDIMENTO
			&& $order->provider != null &&
			$this->sessao->getIdUsuario() === $order->provider->id
		) {
			return true;
		}

		return false;
	}

	public function possoReservar($order)
	{
		if ($this->sessao->getNivelAcesso() === Sessao::NIVEL_ADM
		 && (
				$order->statos === self::STATUS_ABERTO
				||
				$order->statos === self::STATUS_REABERTO
				||
				$order->statos === self::STATUS_ATENDIMENTO
				||
				$order->statos === self::STATUS_AGUARDANDO_ATIVO
				||
				$order->statos === self::STATUS_AGUARDANDO_USUARIO
		 	)
		) {
			return true;
		}
		return false;
	}

	public function possoEditarPatrimonio($order)
	{
		$this->sessao = new Sessao();

		if ($order->status == self::STATUS_FECHADO) {
			return false;
		}
		if ($order->status == self::STATUS_CANCELADO) {
			return false;
		}
		if ($order->status == self::STATUS_FECHADO_CONFIRMADO) {
			return false;
		}
		if ($this->sessao->getIdUsuario() == $order->customer->id) {
			return true;
		}
		if ($order->provider != null && $this->sessao->getIdUsuario() == $order->provider->id) {
			return true;
		}
	}
	public function possoAvaliar($order)
	{
		if ($order->status === self::STATUS_FECHADO
		&& $this->sessao->getIdUsuario() === $order->customer->id
		&& $order->customer != null) {
			return true;
		}

		return false;
	}
	public function possoReabrir($order)
	{
		//Só permitir isso se o usuário for cliente do chamado
		//O chamado deve estar fechado.
		if ($order->customer != null && $order->status != self::STATUS_FECHADO && $this->sessao->getIdUsuario() != $order->customer->id) {
			return true;
		}
		return false;
	}
	const STATUS_ABERTO = 'opened';
	const STATUS_RESERVADO = 'reserved';
	const STATUS_AGUARDANDO_USUARIO = 'pending customer response';
	const STATUS_ATENDIMENTO = 'in progress';
	const STATUS_FECHADO = 'closed';
	const STATUS_FECHADO_CONFIRMADO = 'committed';
	const STATUS_CANCELADO = 'canceled';
	const STATUS_AGUARDANDO_ATIVO = 'pending it resource';
	const STATUS_REABERTO = 'reopened';

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
		echo ':sucess:' . $sessao->getNivelAcesso();
		return;
	}

	public function painel($lista, $strTitulo, $id, $strShow = "")
	{
		echo view(
			'partials.index-orders',
			[
				'orders' => $lista,
				'id' => $id,
				'title' => $strTitulo,
				'strShow' => $strShow
			]
		);
	}

	public function arrayStatusPendente()
	{
		$arrStatus = array();
		$arrStatus[] = StatusOcorrenciaController::STATUS_ABERTO;
		$arrStatus[] = StatusOcorrenciaController::STATUS_AGUARDANDO_ATIVO;
		$arrStatus[] = StatusOcorrenciaController::STATUS_AGUARDANDO_USUARIO;
		$arrStatus[] = StatusOcorrenciaController::STATUS_ATENDIMENTO;
		$arrStatus[] = StatusOcorrenciaController::STATUS_REABERTO;
		$arrStatus[] = StatusOcorrenciaController::STATUS_RESERVADO;
		return $arrStatus;
	}
	public function arrayStatusFinalizado()
	{

		$arrStatus = array();
		$arrStatus[] = StatusOcorrenciaController::STATUS_FECHADO;
		$arrStatus[] = StatusOcorrenciaController::STATUS_FECHADO_CONFIRMADO;
		$arrStatus[] = StatusOcorrenciaController::STATUS_CANCELADO;
		return $arrStatus;
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
			$data1 = date("Y-m-d", strtotime($_GET['data_abertura1']));
			$query = $query->where('orders.created_at', '>=', $data1);
		}
		if (isset($_GET['data_abertura2'])) {
			$data2 = date("Y-m-d", strtotime($_GET['data_abertura2']));
			$query = $query->where('orders.created_at', '<=', $data2);
		}
		if (isset($_GET['campus'])) {
			$campusArr = explode(",", $_GET['campus']);
			$query = $query->whereIn('campus', $campusArr);
		}
		if (isset($_GET['setores_responsaveis'])) {
			$divisions = explode(",", $_GET['setores_responsaveis']);
			$query = $query->whereIn('orders.division_id', $divisions);
		}
		if (isset($_GET['setores_requisitantes'])) {
			$divisionsSig = explode(",", $_GET['setores_requisitantes']);
			$query = $query->whereIn('division_sig_id', $divisionsSig);
		}

		return $query;
	}

	public function create()
	{
		$this->sessao = new Sessao();

		$ocorrencia = new Ocorrencia();
		$ocorrencia->getUsuarioCliente()->setId($this->sessao->getIdUsuario());


		$listaNaoAvaliados = Order::where('customer_user_id', $this->sessao->getIdUsuario())->where('status', StatusOcorrenciaController::STATUS_FECHADO)->get();

		echo '
            <div class="row">
                <div class="col-md-12 blog-main">';


		$queryService = DB::table('services');
		if ($this->sessao->getNivelAcesso() == Sessao::NIVEL_COMUM) {
			$queryService->where('role', Sessao::NIVEL_COMUM);
		}
		if ($this->sessao->getNivelAcesso() == Sessao::NIVEL_ADM || $this->sessao->getNivelAcesso() == Sessao::NIVEL_TECNICO) {
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
					'strShow' => 'show'
				]
			);
		}
		echo '
                </div>
            </div>';
	}



}
