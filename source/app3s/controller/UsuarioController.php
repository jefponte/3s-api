<?php
            
/**
 * Classe feita para manipulação do objeto UsuarioController
 * feita automaticamente com programa gerador de software inventado por
 * @author Jefferson Uchôa Ponte <j.pontee@gmail.com>
 */

namespace app3s\controller;

use app3s\dao\AreaResponsavelDAO;
use app3s\dao\UsuarioDAO;
use app3s\model\Usuario;
use app3s\util\Sessao;
use app3s\view\UsuarioView;


class UsuarioController {

	protected  $view;
    protected $dao;

	public function __construct(){
		$this->dao = new UsuarioDAO();
		$this->view = new UsuarioView();
	}

	
	public function mudarNivel(){
		
		$sessao = new Sessao();
		if($sessao->getNIvelOriginal() == Sessao::NIVEL_ADM){
			$sessao->setNivelDeAcesso($_POST['nivel']);
			echo ':sucess:'.$sessao->getNivelAcesso();
			return;
		}
		if($sessao->getNIvelOriginal() == Sessao::NIVEL_TECNICO){
			if($_POST['nivel'] != Sessao::NIVEL_ADM){
				$sessao->setNivelDeAcesso($_POST['nivel']);
				echo ':sucess:'.$sessao->getNivelAcesso();
				return;
			}
			echo ':falha:';
			return;
		}
		echo ':falha:';
	}
	
	public function ajaxLogin(){
	    if (!isset($_POST['logar'])) {
	        return ":falha";
	    }
	    $usuario = new Usuario();
	    $usuario->setLogin($_POST['usuario']);
	    $usuario->setSenha(md5($_POST['senha']));
	    
	    if ($this->dao->autenticar($usuario)) {
	        
	        $sessao = new Sessao();
	        $sessao->criaSessao($usuario->getId(), $usuario->getNivel(), $usuario->getLogin(), $usuario->getNome(), $usuario->getEmail());
	        
	        $idUnidade = $this->dao->getIdUnidade($usuario);
	        if(count($idUnidade) > 0){
	            foreach($idUnidade as $id => $sigla){
	                $sessao->setIDUnidade($id);
	                $sessao->setUnidade($sigla );
	            }
	        }
	        
	        
	        echo ":sucesso:".$sessao->getNivelAcesso();
	    }else{
	        echo ":falha";
	    }
	}
	
	public function fazerLogin(){
	    if (!isset($_POST['logar'])) {
	        return;
	    }
	    
	    $usuario = new Usuario();
	    $usuario->setLogin($_POST['usuario']);
	    $usuario->setSenha(md5($_POST['senha']));
	    
	    if ($this->dao->autenticar($usuario)) {
	        
	        $sessao = new Sessao();
	        $sessao->criaSessao($usuario->getId(), $usuario->getNivel(), $usuario->getLogin(), $usuario->getNome(), $usuario->getEmail());
	        
	        echo '
<div class="alert alert-success" role="alert">
  Login realizado com Sucesso
</div>
';
	        echo '<META HTTP-EQUIV="REFRESH" CONTENT="3; URL=./">';
	    } else {
	        echo '
<div class="alert alert-danger" role="alert">
  Você errou a senha! Tente novamente!
</div>
	            
';
	    }
	}
	
	public function telaLogin(){
	    echo '
<div class="container">
    <div class="row">
        <div class="card mb-4">
            <div class="card-body">';
	    $this->fazerLogin();
	    $this->view->formLogin();
	    echo '
            </div>
        </div>

    </div>
</div>
';
	}

	public function fetch() 
    {
		$list = $this->dao->fetch();
		$this->view->showList($list);
	}


	public function add() {
            
        if(!isset($_POST['enviar_usuario'])){
            $this->view->showInsertForm();
		    return;
		}
		if (! ( isset ( $_POST ['nome'] ) && isset ( $_POST ['email'] ) && isset ( $_POST ['login'] ) && isset ( $_POST ['senha'] ) && isset ( $_POST ['nivel'] ) && isset ( $_POST ['id_setor'] ))) {
			echo '
                <div class="alert alert-danger" role="alert">
                    Failed to register. Some field must be missing. 
                </div>

                ';
			return;
		}
		$usuario = new Usuario ();
		$usuario->setNome ( $_POST ['nome'] );
		$usuario->setEmail ( $_POST ['email'] );
		$usuario->setLogin ( $_POST ['login'] );
		$usuario->setSenha ( $_POST ['senha'] );
		$usuario->setNivel ( $_POST ['nivel'] );
		$usuario->setIdSetor ( $_POST ['id_setor'] );
            
		if ($this->dao->insert ($usuario ))
        {
			echo '

<div class="alert alert-success" role="alert">
  Sucesso ao inserir Usuario
</div>

';
		} else {
			echo '

<div class="alert alert-danger" role="alert">
  Falha ao tentar Inserir Usuario
</div>

';
		}
        echo '<META HTTP-EQUIV="REFRESH" CONTENT="3; URL=?page=usuario">';
	}



            
	public function addAjax() {
            
        if(!isset($_POST['enviar_usuario'])){
            return;    
        }
        
		    
		
		if (! ( isset ( $_POST ['nome'] ) && isset ( $_POST ['email'] ) && isset ( $_POST ['login'] ) && isset ( $_POST ['senha'] ) && isset ( $_POST ['nivel'] ) && isset ( $_POST ['id_setor'] ))) {
			echo ':incompleto';
			return;
		}
            
		$usuario = new Usuario ();
		$usuario->setNome ( $_POST ['nome'] );
		$usuario->setEmail ( $_POST ['email'] );
		$usuario->setLogin ( $_POST ['login'] );
		$usuario->setSenha ( $_POST ['senha'] );
		$usuario->setNivel ( $_POST ['nivel'] );
		$usuario->setIdSetor ( $_POST ['id_setor'] );
            
		if ($this->dao->insert ( $usuario ))
        {
			$id = $this->dao->getConnection()->lastInsertId();
            echo ':sucesso:'.$id;
            
		} else {
			 echo ':falha';
		}
	}
            
            

            
    public function edit(){
	    if(!isset($_GET['edit'])){
	        return;
	    }
        $selected = new Usuario();
	    $selected->setId(intval($_GET['edit']));
	    $this->dao->fillById($selected);
	    $areaDao = new AreaResponsavelDAO($this->dao->getConnection());
		$setores = $areaDao->fetch();
		
        if(!isset($_POST['edit_usuario'])){
            $this->view->showEditForm($selected, $setores);
            return;
        }

		if (! ( isset ( $_POST ['nivel'] ) && isset ( $_POST ['id_setor'] ))) {
			echo '

			<div class="alert alert-danger" role="alert">
			  Formulário incompleto
			</div>
			
			';
			return;
		}

		$selected->setNivel ( $_POST ['nivel'] );
		$selected->setIdSetor ( $_POST ['id_setor'] );
            
		if ($this->dao->update ($selected ))
        {
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
        

    public function main(){
        
		echo '
	        
        <div class="card mb-4">
            <div class="card-body">

';
        echo '
		<div class="row">';
        echo '<div class="col-xl-12 col-lg-12 col-md-12 col-sm-12">';
        
        if(isset($_GET['edit'])){
            $this->edit();
        }
        $this->fetch();
        
        echo '</div>';
        echo '</div>';
		echo '</div>';
        echo '</div>';
            
    }
    public function mainAjax(){

        $this->addAjax();
        
            
    }


            
}
?>