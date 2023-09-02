<?php

/**
 * Classe feita para manipulação do objeto UsuarioController
 * @author Jefferson Uchôa Ponte <j.pontee@gmail.com>
 */

namespace app3s\controller;

use app3s\dao\AreaResponsavelDAO;
use app3s\dao\UsuarioDAO;
use app3s\model\Usuario;
use app3s\util\Sessao;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class UsuarioController
{

	protected  $view;
	protected $dao;

	public function __construct()
	{
		$this->dao = new UsuarioDAO();
	}


	public function mudarNivel()
	{
		$sessao = new Sessao();
		if (
			$sessao->getNIvelOriginal() != Sessao::NIVEL_TECNICO
			&& $sessao->getNIvelOriginal() != Sessao::NIVEL_ADM)
			{
				echo ':falha:';
				return;
		}
		if ($sessao->getNIvelOriginal() === Sessao::NIVEL_TECNICO
			&& $_POST['nivel'] === Sessao::NIVEL_ADM) {
				echo ':falha:';
				return;
		}
		$sessao->setNivelDeAcesso($_POST['nivel']);
		echo ':sucess:' . $sessao->getNivelAcesso();
		return;
	}

	public function getStrNivel($nivel) {
		$strNivel = 'Desconhecido';
		switch($nivel) {
			case Sessao::NIVEL_ADM:
				$strNivel = 'Administrador';
				break;
			case Sessao::NIVEL_TECNICO:
				$strNivel = 'Técnico';
				break;
			case Sessao::NIVEL_COMUM:
				$strNivel = 'Comum';
				break;
			default:
				$strNivel = 'Desconhecido';
			break;
		}
		return $strNivel;
	}

	public function fetch()
	{
		$users = User::get();
		foreach($users as $user) {
			$user->strNivel = $this->getStrNivel($user->role);
		}
		echo view('partials.index-users', ['users' => $users]);
	}




	public function edit()
	{
		if (!isset($_GET['edit'])) {
			return;
		}
		$selected = new Usuario();
		$selected->setId(intval($_GET['edit']));


		$setores = DB::table('area_responsavel')->get();
		$user = User::findOrFail(intval($_GET['edit']));
		if (!isset($_POST['edit_usuario'])) {
			echo view('partials.form-edit-user', ['user' => $user, 'divisions' => $setores]);
			return;
		}

		if (!(isset($_POST['nivel']) && isset($_POST['id_setor']))) {
			echo '

			<div class="alert alert-danger" role="alert">
			  Formulário incompleto
			</div>

			';
			return;
		}

		$user->role = $_POST['nivel'];
		$user->division_id = $_POST['id_setor'];


		if ($user->save()) {
			echo '

<div class="alert alert-success" role="alert">
  Sucesso ao alterar usuário.
</div>

';
		} else {
			echo '

<div class="alert alert-danger" role="alert">
  Falha ao tentar alterar usuário
</div>

';
		}
		echo '<META HTTP-EQUIV="REFRESH" CONTENT="3; URL=?page=usuario">';
	}


	public function main()
	{

		echo '

        <div class="card mb-4">
            <div class="card-body">
				<div class="row">
					<div class="col-xl-12 col-lg-12 col-md-12 col-sm-12">';

		if (isset($_GET['edit'])) {
			$this->edit();
		}
		$this->fetch();

		echo '		</div>
				</div>
			</div>
		</div>';
	}
}
