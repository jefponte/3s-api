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



}
