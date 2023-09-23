<?php

/**
 * Classe feita para manipulação do objeto OcorrenciaController
 * @author Jefferson Uchôa Ponte <j.pontee@gmail.com>
 */

namespace app3s\controller;

use Illuminate\Support\Facades\Http;
use app3s\dao\OcorrenciaDAO;
use app3s\model\Ocorrencia;
use app3s\util\Mail;
use app3s\util\Sessao;
use App\Models\Division;
use App\Models\Order;
use App\Models\OrderStatusLog;
use App\Models\Service;
use App\Models\User;
use Illuminate\Support\Facades\DB;

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
		if (
			$this->sessao->getNivelAcesso() === Sessao::NIVEL_ADM
			&&
			($order->status == self::STATUS_ATENDIMENTO
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
		if (
			$this->sessao->getNivelAcesso() === Sessao::NIVEL_ADM
			&& ($order->statos === self::STATUS_ABERTO
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
		if (
			$order->status === self::STATUS_FECHADO
			&& $this->sessao->getIdUsuario() === $order->customer->id
			&& $order->customer != null
		) {
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
		$arrStatus[] = self::STATUS_ABERTO;
		$arrStatus[] = self::STATUS_AGUARDANDO_ATIVO;
		$arrStatus[] = self::STATUS_AGUARDANDO_USUARIO;
		$arrStatus[] = self::STATUS_ATENDIMENTO;
		$arrStatus[] = self::STATUS_REABERTO;
		$arrStatus[] = self::STATUS_RESERVADO;
		return $arrStatus;
	}
	public function arrayStatusFinalizado()
	{

		$arrStatus = array();
		$arrStatus[] = self::STATUS_FECHADO;
		$arrStatus[] = self::STATUS_FECHADO_CONFIRMADO;
		$arrStatus[] = self::STATUS_CANCELADO;
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


		$listaNaoAvaliados = Order::where('customer_user_id', $this->sessao->getIdUsuario())->where(
			'status',
			self::STATUS_FECHADO
		)->get();

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
	public function passwordVerify()
	{
		$this->sessao = new Sessao();
		if (!isset($_POST['senha'])) {
			return false;
		}
		$login = $this->sessao->getLoginUsuario();
		$senha = $_POST['senha'];
		$data = ['login' =>  $login, 'senha' => $senha];
		$response = Http::post(env('UNILAB_API_ORIGIN') . '/authenticate', $data);
		$responseJ = json_decode($response->body());

		$idUsuario  = 0;

		if (isset($responseJ->id)) {
			$idUsuario = intval($responseJ->id);
		}
		if ($idUsuario === 0) {
			return false;
		}
		if ($responseJ->id != $this->sessao->getIdUsuario()) {
			echo ":falha:Senha Incorreta.";
			return false;
		}
		return true;
	}
	public function mainAjaxStatus()
	{
		if (!isset($_POST['status_acao'])) {
			echo ':falha:Ação não especificada';
			return;
		}
		if (!$this->passwordVerify()) {
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
			'service.division'
		]);
		$status = false;
		$mensagem = "";
		switch ($_POST['status_acao']) {
			case 'cancelar':
				$status = $this->ajaxCancelar($order);
				$mensagem = '<p>Chamado cancelado</p>';
				break;
			case 'atender':
				$status = $this->ajaxAtender($order);
				$mensagem = '<p>Chamado em atendimento</p>';
				break;
			case 'fechar':
				$status = $this->ajaxFechar($order);
				$mensagem = '<p>Chamado fechado</p>';
				break;
			case 'reservar':
				$status = $this->ajaxReservar($order);
				$mensagem = '<p>Chamado reservado</p>';
				break;
			case 'liberar_atendimento':
				$status = $this->ajaxLiberar($order);
				$mensagem = '<p>Chamado Liberado para atendimento</p>';
				break;
			case 'avaliar':
				$status = $this->ajaxAvaliar($order);
				$mensagem = '<p>Chamado avaliado</p>';
				break;
			case 'reabrir':
				$status = $this->ajaxReabrir($order);
				$mensagem = '<p>Chamado reaberto</p>';
				break;
			case 'editar_servico':
				$status = $this->ajaxEditarServico($order);
				$mensagem = '<p>Serviço alterado</p>';
				break;
			case 'editar_solucao':
				$status = $this->ajaxEditarSolucao($order);
				$mensagem = '<p>Solução editada</p>';
				break;
			case 'aguardar_ativos':
				$status = $this->ajaxAguardandoAtivo($order);
				$mensagem = '<p>Aguardando ativo de TI</p>';
				break;
			case 'aguardar_usuario':
				$status = $this->ajaxAguardandoUsuario($order);
				$mensagem = '<p>Aguardando resposta do cliente</p>';
				break;
			case 'editar_patrimonio':
				$status = $this->ajaxEditarPatrimonio($order);
				$mensagem = '<p>Patrimônio editado.</p>';
				break;
			default:
				echo ':falha:Ação não encontrada';

				break;
		}
		if ($status) {
			$this->enviarEmail($order, $mensagem);
		}
	}

	public function ajaxAtender($order)
	{
		$this->sessao = new Sessao();
		if (!isset($_POST['status_acao'])) {
			return false;
		}
		if ($_POST['status_acao'] != 'atender') {
			return false;
		}
		if (!isset($_POST['id_ocorrencia'])) {
			return false;
		}
		if (!isset($_POST['senha'])) {
			return false;
		}

		if (!$this->possoAtender($order)) {
			echo ':falha:Não é possível atender este chamado.';
			return false;
		}

		DB::beginTransaction();
		try {
			OrderStatusLog::create([
				'order_id' => $order->id,
				'status' => self::STATUS_ATENDIMENTO,
				'message' => 'Chamado em atendimento',
				'user_id' => auth()->user()->id
			]);
			$order->status = self::STATUS_ATENDIMENTO;
			$order->division_id = auth()->user()->division_id;
			$order->provider_user_id = auth()->user()->id;
			$order->save();
			DB::commit();
			echo ':sucesso:' . $order->id . ':Chamado am atendimento!';
		} catch (\Exception $e) {
			DB::rollback();
			echo ':falha:Falha ao tentar inserir histórico.';
		}
	}


	public function ajaxCancelar($order)
	{
		if (!isset($_POST['status_acao'])) {
			return false;
		}
		// if ($_POST['status_acao'] != 'cancelar') {
		// 	return false;
		// }
		// if (!isset($_POST['id_ocorrencia'])) {
		// 	return false;
		// }
		// if (!isset($_POST['senha'])) {
		// 	return false;
		// }

		// $order = Order::findOrFail($_POST['id_ocorrencia']);

		// if (!$this->possoCancelar($order)) {
		// 	echo ":falha:Este chamado não pode ser cancelado.";
		// 	return false;
		// }


		// $ocorrenciaDao = new OcorrenciaDAO($this->dao->getConnection());
		// $this->ocorrencia->setStatus(self::STATUS_CANCELADO);

		// $status = new Status();
		// $status->setSigla(self::STATUS_CANCELADO);

		// $statusDao = new StatusDAO($this->dao->getConnection());
		// $statusDao->fillBySigla($status);

		// $this->statusOcorrencia = new StatusOcorrencia();
		// $this->statusOcorrencia->setOcorrencia($this->ocorrencia);
		// $this->statusOcorrencia->setStatus($status);
		// $this->statusOcorrencia->setDataMudanca(date("Y-m-d G:i:s"));
		// $this->statusOcorrencia->getUsuario()->setId($this->sessao->getIdUsuario());
		// $this->statusOcorrencia->setMensagem("Ocorrência cancelada pelo usuário");


		// $ocorrenciaDao->getConnection()->beginTransaction();

		// if (!$ocorrenciaDao->update($this->ocorrencia)) {
		// 	echo ':falha:Falha na alteração do status da ocorrência.';
		// 	$ocorrenciaDao->getConnection()->rollBack();
		// 	return false;
		// }

		// if (!$this->dao->insert($this->statusOcorrencia)) {
		// 	echo ':falha:Falha ao tentar inserir histórico.';
		// 	return false;
		// }
		// $ocorrenciaDao->getConnection()->commit();
		echo ':sucesso:' . $order->id . ':Chamado cancelado com sucesso!';
		return true;
	}

	public function ajaxFechar($order)
	{
		if (!$this->possoFechar($order)) {
			echo ':falha:Não é possível fechar este chamado.';
			return false;
		}

		// $usuario = new Usuario();
		// $usuario->setId($this->sessao->getIdUsuario());

		// $usuarioDao = new UsuarioDAO($this->dao->getConnection());
		// $usuarioDao->fillById($usuario);
		// $this->ocorrencia->getAreaResponsavel()->setId($usuario->getIdSetor());


		// $ocorrenciaDao = new OcorrenciaDAO($this->dao->getConnection());
		// $this->ocorrencia->setStatus(self::STATUS_FECHADO);

		// $status = new Status();
		// $status->setSigla(self::STATUS_FECHADO);

		// $statusDao = new StatusDAO($this->dao->getConnection());
		// $statusDao->fillBySigla($status);

		// $this->statusOcorrencia = new StatusOcorrencia();
		// $this->statusOcorrencia->setOcorrencia($this->ocorrencia);
		// $this->statusOcorrencia->setStatus($status);
		// $this->statusOcorrencia->setDataMudanca(date("Y-m-d G:i:s"));
		// $this->statusOcorrencia->getUsuario()->setId($this->sessao->getIdUsuario());
		// $this->statusOcorrencia->setMensagem("Ocorrência fechada pelo atendente");


		// $this->ocorrencia->setDataFechamento(date("Y-m-d H:i:s"));


		// $ocorrenciaDao->getConnection()->beginTransaction();

		// if (!$ocorrenciaDao->update($this->ocorrencia)) {
		// 	echo ':falha:Falha na alteração do status da ocorrência.';
		// 	$ocorrenciaDao->getConnection()->rollBack();
		// 	return false;
		// }

		// if (!$this->dao->insert($this->statusOcorrencia)) {
		// 	echo ':falha:Falha ao tentar inserir histórico.';
		// 	return false;
		// }
		// $ocorrenciaDao->getConnection()->commit();
		echo ':sucesso:' . $order->id . ':Chamado fechado com sucesso!';

		return true;
	}
	public function ajaxAvaliar($order)
	{
		// if (!isset($_POST['avaliacao'])) {
		// 	echo ':falha:Faça uma avaliação';
		// 	return false;
		// }
		// if (!$this->possoAvaliar($order)) {
		// 	return false;
		// }

		// $ocorrenciaDao = new OcorrenciaDAO($this->dao->getConnection());
		// $this->ocorrencia->setStatus(self::STATUS_FECHADO_CONFIRMADO);

		// $status = new Status();
		// $status->setSigla(self::STATUS_FECHADO_CONFIRMADO);

		// $statusDao = new StatusDAO($this->dao->getConnection());
		// $statusDao->fillBySigla($status);

		// $this->statusOcorrencia = new StatusOcorrencia();
		// $this->statusOcorrencia->setOcorrencia($this->ocorrencia);
		// $this->statusOcorrencia->setStatus($status);
		// $this->statusOcorrencia->setDataMudanca(date("Y-m-d G:i:s"));
		// $this->statusOcorrencia->getUsuario()->setId($this->sessao->getIdUsuario());
		// $this->statusOcorrencia->setMensagem("Atendimento avaliado pelo cliente");

		// $this->ocorrencia->setDataFechamentoConfirmado(date("Y-m-d H:i:s"));
		// $this->ocorrencia->setAvaliacao($_POST['avaliacao']);

		// $ocorrenciaDao->getConnection()->beginTransaction();



		// if (!$ocorrenciaDao->update($this->ocorrencia)) {
		// 	echo ':falha:Falha na alteração do status da ocorrência.';
		// 	$ocorrenciaDao->getConnection()->rollBack();
		// 	return false;
		// }

		// if (!$this->dao->insert($this->statusOcorrencia)) {
		// 	echo ':falha:Falha ao tentar inserir histórico.';
		// 	return false;
		// }
		// $ocorrenciaDao->getConnection()->commit();

		echo ':sucesso:' . $order->id . ':Atendimento avaliado com sucesso!';
		return true;
	}
	public function ajaxReabrir($order)
	{
		// if (!$this->possoReabrir($order)) {
		// 	return false;
		// }

		// $ocorrenciaDao = new OcorrenciaDAO($this->dao->getConnection());
		// $this->ocorrencia->setStatus(self::STATUS_REABERTO);

		// $status = new Status();
		// $status->setSigla(self::STATUS_REABERTO);

		// $statusDao = new StatusDAO($this->dao->getConnection());
		// $statusDao->fillBySigla($status);

		// $this->statusOcorrencia = new StatusOcorrencia();
		// $this->statusOcorrencia->setOcorrencia($this->ocorrencia);
		// $this->statusOcorrencia->setStatus($status);
		// $this->statusOcorrencia->setDataMudanca(date("Y-m-d G:i:s"));
		// $this->statusOcorrencia->getUsuario()->setId($this->sessao->getIdUsuario());
		// $this->statusOcorrencia->setMensagem("Ocorrência Reaberta pelo cliente");
		// if (isset($_POST['mensagem-status'])) {
		// 	$this->statusOcorrencia->setMensagem($_POST['mensagem-status']);
		// }


		// $ocorrenciaDao->getConnection()->beginTransaction();



		// if (!$ocorrenciaDao->update($this->ocorrencia)) {
		// 	echo ':falha:Falha na alteração do status da ocorrência.';
		// 	$ocorrenciaDao->getConnection()->rollBack();
		// 	return false;
		// }

		// if (!$this->dao->insert($this->statusOcorrencia)) {
		// 	echo ':falha:Falha ao tentar inserir histórico.';
		// 	return false;
		// }
		// $ocorrenciaDao->getConnection()->commit();

		echo ':sucesso:' . $order->id . ':Atendimento reaberto com sucesso!';
		return true;
	}



	public function ajaxReservar($order)
	{
		// if (!isset($_POST['tecnico'])) {
		// 	echo ':falha:Técnico especificado';
		// 	return false;
		// }
		// if (!$this->possoReservar($order)) {
		// 	echo ':falha:Você não pode reservar esse chamado.';
		// 	return false;
		// }

		// $ocorrenciaDao = new OcorrenciaDAO($this->dao->getConnection());
		// $ocorrenciaDao->fillById($this->ocorrencia);

		// $usuario = new Usuario();
		// $usuario->setId($_POST['tecnico']);

		// $usuarioDao = new UsuarioDAO($this->dao->getConnection());
		// $usuarioDao->fillById($usuario);

		// $this->ocorrencia->getAreaResponsavel()->setId($usuario->getIdSetor());
		// $this->ocorrencia->setStatus(self::STATUS_RESERVADO);

		// $status = new Status();
		// $status->setSigla(self::STATUS_RESERVADO);

		// $statusDao = new StatusDAO($this->dao->getConnection());
		// $statusDao->fillBySigla($status);

		// $this->statusOcorrencia = new StatusOcorrencia();
		// $this->statusOcorrencia->setOcorrencia($this->ocorrencia);
		// $this->statusOcorrencia->setStatus($status);
		// $this->statusOcorrencia->setDataMudanca(date("Y-m-d G:i:s"));
		// $this->statusOcorrencia->getUsuario()->setId($this->sessao->getIdUsuario());
		// $this->statusOcorrencia->setMensagem('Atendimento reservado para ' . $usuario->getNome());


		// $ocorrenciaDao->getConnection()->beginTransaction();
		// $this->ocorrencia->setIdUsuarioIndicado($usuario->getId());
		// $this->ocorrencia->getAreaResponsavel()->setId($usuario->getIdSetor());


		// if (!$ocorrenciaDao->update($this->ocorrencia)) {
		// 	echo ':falha:Falha na alteração do status da ocorrência.';
		// 	$ocorrenciaDao->getConnection()->rollBack();
		// 	return false;
		// }

		// if (!$this->dao->insert($this->statusOcorrencia)) {
		// 	echo ':falha:Falha ao tentar inserir histórico.';
		// 	return false;
		// }
		// $ocorrenciaDao->getConnection()->commit();

		echo ':sucesso:' . $order->id . ':Reservado com sucesso!';
		return true;
	}

	public function ajaxEditarPatrimonio($order)
	{
		// if (!$this->possoEditarPatrimonio($this->ocorrencia)) {
		// 	echo ':falha:Este patrimônio não pode ser editado.';
		// 	return false;
		// }
		// if (!isset($_POST['patrimonio'])) {
		// 	echo ':falha:Digite um patrimônio.';
		// 	return false;
		// }
		// if (trim($_POST['patrimonio']) == "") {
		// 	echo ':falha:Digite um patrimônio.';
		// 	return false;
		// }



		// $ocorrenciaDao = new OcorrenciaDAO($this->dao->getConnection());
		// $status = new Status();
		// $status->setSigla($this->ocorrencia->getStatus());

		// $statusDao = new StatusDAO($this->dao->getConnection());
		// $statusDao->fillBySigla($status);

		// $this->statusOcorrencia = new StatusOcorrencia();
		// $this->statusOcorrencia->setOcorrencia($this->ocorrencia);
		// $this->statusOcorrencia->setStatus($status);
		// $this->statusOcorrencia->setDataMudanca(date("Y-m-d G:i:s"));
		// $this->statusOcorrencia->getUsuario()->setId($this->sessao->getIdUsuario());
		// $this->statusOcorrencia->setMensagem('Técnico editou o Patrimônio para: ' . $_POST['patrimonio'] . '.');

		// $this->ocorrencia->setPatrimonio(strip_tags($_POST['patrimonio']));
		// $ocorrenciaDao->getConnection()->beginTransaction();


		// if (!$ocorrenciaDao->update($this->ocorrencia)) {
		// 	echo ':falha:Falha na alteração do patrimonio da ocorrência.';
		// 	$ocorrenciaDao->getConnection()->rollBack();
		// 	return false;
		// }

		// if (!$this->dao->insert($this->statusOcorrencia)) {
		// 	echo ':falha:Falha ao tentar inserir histórico.';
		// 	return false;
		// }
		// $ocorrenciaDao->getConnection()->commit();

		echo ':sucesso:' . $order->id . ':Patrimonio editado com sucesso!';
		return true;
	}

	public function ajaxAguardandoAtivo($order)
	{

		// if (!$this->possoEditarSolucao($order)) {
		// 	echo ':falha:Esta solução não pode ser editada.';
		// 	return false;
		// }

		// $ocorrenciaDao = new OcorrenciaDAO($this->dao->getConnection());
		// $this->ocorrencia->setStatus(self::STATUS_AGUARDANDO_ATIVO);

		// $status = new Status();
		// $status->setSigla(self::STATUS_AGUARDANDO_ATIVO);

		// $statusDao = new StatusDAO($this->dao->getConnection());
		// $statusDao->fillBySigla($status);

		// $this->statusOcorrencia = new StatusOcorrencia();
		// $this->statusOcorrencia->setOcorrencia($this->ocorrencia);
		// $this->statusOcorrencia->setStatus($status);
		// $this->statusOcorrencia->setDataMudanca(date("Y-m-d G:i:s"));
		// $this->statusOcorrencia->getUsuario()->setId($this->sessao->getIdUsuario());
		// $this->statusOcorrencia->setMensagem("Aguardando ativo de TI");


		// $ocorrenciaDao->getConnection()->beginTransaction();

		// if (!$ocorrenciaDao->update($this->ocorrencia)) {
		// 	echo ':falha:Falha na alteração do status da ocorrência.';
		// 	$ocorrenciaDao->getConnection()->rollBack();
		// 	return false;
		// }

		// if (!$this->dao->insert($this->statusOcorrencia)) {
		// 	echo ':falha:Falha ao tentar inserir histórico.';
		// 	return false;
		// }
		// $ocorrenciaDao->getConnection()->commit();
		echo ':sucesso:' . $order->id . ':Alterado para aguardando ativo de ti!';
		return true;
	}
	public function ajaxAguardandoUsuario($order)
	{
		// if (!$this->possoEditarSolucao($this->ocorrencia)) {
		// 	echo ':falha:Esta solução não pode ser editada.';
		// 	return false;
		// }
		// $ocorrenciaDao = new OcorrenciaDAO($this->dao->getConnection());
		// $this->ocorrencia->setStatus(self::STATUS_AGUARDANDO_USUARIO);

		// $status = new Status();
		// $status->setSigla(self::STATUS_AGUARDANDO_USUARIO);

		// $statusDao = new StatusDAO($this->dao->getConnection());
		// $statusDao->fillBySigla($status);

		// $this->statusOcorrencia = new StatusOcorrencia();
		// $this->statusOcorrencia->setOcorrencia($this->ocorrencia);
		// $this->statusOcorrencia->setStatus($status);
		// $this->statusOcorrencia->setDataMudanca(date("Y-m-d G:i:s"));
		// $this->statusOcorrencia->getUsuario()->setId($this->sessao->getIdUsuario());
		// $this->statusOcorrencia->setMensagem("Aguardando Usuário");


		// $ocorrenciaDao->getConnection()->beginTransaction();

		// if (!$ocorrenciaDao->update($this->ocorrencia)) {
		// 	echo ':falha:Falha na alteração do status da ocorrência.';
		// 	$ocorrenciaDao->getConnection()->rollBack();
		// 	return false;
		// }

		// if (!$this->dao->insert($this->statusOcorrencia)) {
		// 	echo ':falha:Falha ao tentar inserir histórico.';
		// 	return false;
		// }
		// $ocorrenciaDao->getConnection()->commit();
		echo ':sucesso:' . $order->id . ':Alterado para aguardando usuário!';
		return true;
	}

	public function ajaxEditarSolucao($order)
	{
		// if (!$this->possoEditarSolucao($this->ocorrencia)) {
		// 	echo ':falha:Esta solução não pode ser editada.';
		// 	return false;
		// }
		// if (!isset($_POST['solucao'])) {
		// 	echo ':falha:Digite uma solução.';
		// 	return false;
		// }
		// if (trim($_POST['solucao']) == "") {
		// 	echo ':falha:Digite uma solução.';
		// 	return false;
		// }



		// $ocorrenciaDao = new OcorrenciaDAO($this->dao->getConnection());
		// $status = new Status();
		// $status->setSigla($this->ocorrencia->getStatus());

		// $statusDao = new StatusDAO($this->dao->getConnection());
		// $statusDao->fillBySigla($status);

		// $this->statusOcorrencia = new StatusOcorrencia();
		// $this->statusOcorrencia->setOcorrencia($this->ocorrencia);
		// $this->statusOcorrencia->setStatus($status);
		// $this->statusOcorrencia->setDataMudanca(date("Y-m-d G:i:s"));
		// $this->statusOcorrencia->getUsuario()->setId($this->sessao->getIdUsuario());
		// $this->statusOcorrencia->setMensagem('Técnico editou a solução. ');

		// $this->ocorrencia->setSolucao(strip_tags($_POST['solucao']));
		// $ocorrenciaDao->getConnection()->beginTransaction();


		// if (!$ocorrenciaDao->update($this->ocorrencia)) {
		// 	echo ':falha:Falha na alteração do status da ocorrência.';
		// 	$ocorrenciaDao->getConnection()->rollBack();
		// 	return false;
		// }

		// if (!$this->dao->insert($this->statusOcorrencia)) {
		// 	echo ':falha:Falha ao tentar inserir histórico.';
		// 	return false;
		// }
		// $ocorrenciaDao->getConnection()->commit();

		echo ':sucesso:' . $order->id . ':Solução editada com sucesso!';
		return true;
	}


	public function ajaxEditarServico($order)
	{
		// if (!$this->possoEditarServico($this->ocorrencia)) {
		// 	echo ':falha:Este serviço não pode ser editado.';
		// 	return false;
		// }

		// if (!isset($_POST['id_servico'])) {
		// 	echo ':falha:Selecione um serviço.';
		// 	return false;
		// }


		// $servico = new Servico();
		// $servico->setId($_POST['id_servico']);

		// $servicoDao = new ServicoDAO($this->dao->getConnection());
		// $servicoDao->fillById($servico);


		// $ocorrenciaDao = new OcorrenciaDAO($this->dao->getConnection());


		// $status = new Status();
		// $status->setSigla($this->ocorrencia->getStatus());

		// $statusDao = new StatusDAO($this->dao->getConnection());
		// $statusDao->fillBySigla($status);

		// $this->statusOcorrencia = new StatusOcorrencia();
		// $this->statusOcorrencia->setOcorrencia($this->ocorrencia);
		// $this->statusOcorrencia->setStatus($status);
		// $this->statusOcorrencia->setDataMudanca(date("Y-m-d G:i:s"));
		// $this->statusOcorrencia->getUsuario()->setId($this->sessao->getIdUsuario());
		// $this->statusOcorrencia->setMensagem('Técnico editou o serviço ');

		// $this->ocorrencia->getAreaResponsavel()->setId($servico->getAreaResponsavel()->getId());
		// $this->ocorrencia->getServico()->setId($servico->getId());



		// $ocorrenciaDao->getConnection()->beginTransaction();




		// if (!$ocorrenciaDao->update($this->ocorrencia)) {
		// 	echo ':falha:Falha na alteração do status da ocorrência.';
		// 	$ocorrenciaDao->getConnection()->rollBack();
		// 	return false;
		// }

		// if (!$this->dao->insert($this->statusOcorrencia)) {
		// 	echo ':falha:Falha ao tentar inserir histórico.';
		// 	return false;
		// }
		// $ocorrenciaDao->getConnection()->commit();

		echo ':sucesso:' . $order->id . ':Serviço editado com sucesso!';
		return true;
	}

	public function ajaxLiberar($order)
	{

		// if (!$this->possoLiberar($order)) {
		// 	echo ':falha:Não é possível liberar esse atendimento.';
		// 	return false;
		// }

		// $ocorrenciaDao = new OcorrenciaDAO($this->dao->getConnection());
		// $this->ocorrencia->setStatus(self::STATUS_ABERTO);

		// $status = new Status();
		// $status->setSigla(self::STATUS_ABERTO);

		// $statusDao = new StatusDAO($this->dao->getConnection());
		// $statusDao->fillBySigla($status);

		// $this->statusOcorrencia = new StatusOcorrencia();
		// $this->statusOcorrencia->setOcorrencia($this->ocorrencia);
		// $this->statusOcorrencia->setStatus($status);
		// $this->statusOcorrencia->setDataMudanca(date("Y-m-d G:i:s"));
		// $this->statusOcorrencia->getUsuario()->setId($this->sessao->getIdUsuario());
		// $this->statusOcorrencia->setMensagem('Liberado para atendimento');


		// $ocorrenciaDao->getConnection()->beginTransaction();
		// $this->ocorrencia->setIdUsuarioIndicado(null);
		// $this->ocorrencia->setIdUsuarioAtendente(null);

		// $servicoDao = new ServicoDAO($this->dao->getConnection());
		// $servicoDao->fillById($this->ocorrencia->getServico());


		// $this->ocorrencia->getAreaResponsavel()->setId($this->ocorrencia->getServico()->getAreaResponsavel()->getId());

		// if (!$ocorrenciaDao->update($this->ocorrencia)) {
		// 	echo ':falha:Falha na alteração do status da ocorrência.';
		// 	$ocorrenciaDao->getConnection()->rollBack();
		// 	return false;
		// }

		// if (!$this->dao->insert($this->statusOcorrencia)) {
		// 	echo ':falha:Falha ao tentar inserir histórico.';
		// 	return false;
		// }
		// $ocorrenciaDao->getConnection()->commit();

		echo ':sucesso:' . $order->getId() . ':Liberado com sucesso!';

		return true;
	}


	public function enviarEmail($order, $mensagem = "")
	{
		$mail = new Mail();
		$assunto = "[3S] - Chamado Nº " . $order->id;



		$saldacao =  '<p>Prezado(a),</p>';

		$corpo = '<p>Avisamos que houve uma mudança no status da solicitação
		<a href="https://3s.unilab.edu.br/?page=ocorrencia&selecionar=' .
			$order->id . '">Nº' . $order->id . '</a></p>';
		$corpo .= $mensagem;
		$corpo .= '<ul>
                        <li>Serviço Solicitado: ' . $order->service->name . '</li>
                        <li>Descrição do Problema: ' . $order->description . '</li>
                        <li>Setor Responsável: ' . $order->division->name . ' -
                        ' . $order->division->description . '</li>
                        <li>Cliente: ' . $order->customer->name . '</li>
                </ul><br><p>Mensagem enviada pelo sistema 3S. Favor não responder.</p>';


		$destinatario = $order->email;
		$nome = $order->customer->name;
		//Cliente do chamado
		$mail->enviarEmail(auth()->user()->email, auth()->user()->name, $assunto, $saldacao . $corpo);



		//Area responsável
		$destinatario = $order->division->email;
		$nome = $order->division->name;
		$mail->enviarEmail($destinatario, $nome, $assunto, $saldacao . $corpo); //Email para area responsavel
		if ($order->provider != null) {
			//O atendente;
			$destinatario = $order->provider->email;
			$nome = $order->provider->name;
			$mail->enviarEmail($destinatario, $nome, $assunto, $saldacao . $corpo);
		}
	}
}
