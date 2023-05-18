<?php

/**
 * Classe de visao para Usuario
 * @author Jefferson Uchôa Ponte <j.pontee@gmail.com>
 *
 */

namespace app3s\view;

use app3s\model\Usuario;


class UsuarioView
{


  public function formLogin()
  {
    echo '
        
<br><br>

<div class="container forget-password">
<div class="row justify-content-center">
        <div class="col-xl-4 col-lg-4 col-md-7 col-sm-12">
            <div class="panel-body p-3 m-3">
                <div class="text-center">

                            
                            <div class="panel">
                                <h3>Entrar no sistema 3s</h3>
                               
                            </div>
                            <form id="login-form" class="form" method="post" action=".">
                    
                                <div class="form-group">
                                    <input type="text" size="350" name="usuario" class="form-control" id="usuario" placeholder="Usuario do SIG" autofocus="autofocus">
                                </div>
                    
                                <div class="form-group">
                                    <input type="password" class="form-control" name="senha" size="100" id="senha" placeholder="Senha do SIG">
                                </div>
                                <div class="forgot">
                                    <a href="https://sigadmin.unilab.edu.br/admin/public/recuperar_senha.jsf">Esqueceu a senha?</a>
                                </div>
                                <button type="submit" id="botao-login" class="btn-primary btn-lg btn-block">
                                    <span id="spinner-submit" class="escondido spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
                                    Entrar
                                </button>
                                
                                <input type="hidden" class="btn btn-info btn-md" name="logar" value="Entrar">
                            </form>


            </div>
        </div>
    </div>
</div>
</div>
<br><br><br><br><br><br><br><br>


';
  }


  public function showList($lista)
  {
    echo '
                                            
                                            
                                            

          <div class="card">
                <div class="card-header">
                  Lista Usuario
                </div>
                <div class="card-body">
                                            
                                            
		<div class="table-responsive">
			<table class="table table-bordered" id="dataTable" width="100%"
				cellspacing="0">
				<thead>
					<tr>
						<th>Id</th>
						<th>Nome</th>
						<th>Nível de Acesso</th>
                        <th>Actions</th>
					</tr>
				</thead>
				<tfoot>
					<tr>
                        <th>Id</th>
                        <th>Nome</th>
                        <th>Nível de Acesso</th>
                        <th>Actions</th>
					</tr>
				</tfoot>
				<tbody>';

    foreach ($lista as $element) {
      echo '<tr>';
      echo '<td>' . $element->getId() . '</td>';
      echo '<td>' . $element->getNome() . '</td>';
      echo '<td>' . $element->getStrNivel() . '</td>';
      echo '<td>
                        <a href="?page=usuario&edit=' . $element->getId() . '" class="btn btn-success text-white">Editar</a>
                      </td>';
      echo '</tr>';
    }

    echo '
				</tbody>
			</table>
		</div>
            
            
            
            
  </div>
</div>
            
';
  }



  public function showEditForm(Usuario $selecionado, $setores)
  {
    echo '
	    
	    

    <div class="card">
        <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
            <h6 class="m-0 font-weight-bold text-primary">Editar Usuário: ' . $selecionado->getNome() . '</h6>
        </div>
        <div class="card-body">
            <form class="user" method="post" id="edit_form_usuario">
                                        
                    <div class="form-group">
                        <label for="select-nivel">Nivel</label>
                        
                        <select id="select-nivel" required name="nivel">
                            <option value="">Nível de Acesso</option>
                            <option value="c" ' . ($selecionado->getNivel() == 'c' ? 'selected' : '') . '>Comum</option>
                            <option value="t" ' . ($selecionado->getNivel() == 't' ? 'selected' : '') . '>Técnico</option>
                            <option value="a" ' . ($selecionado->getNivel() == 'a' ? 'selected' : '') . '>Administrador</option>
                            <option value="d" ' . ($selecionado->getNivel() == 'd' ? 'selected' : '') . '>Desativado</option>
                            ';

    echo '
                        </select>
                    </div>';

    echo '
                    <div class="form-group">
                        <label for="select-unidade">Unidade </label>
                            <select name="id_setor" id="select-unidade">
                                  <option value="">Selecione a Unidade</option>';
    foreach ($setores as $area) {

      echo '
                                    <option value="' . $area->getId() . '" ' . ($selecionado->getIdSetor() === $area->getId() ? 'selected' : '') . '>' . $area->getNome() . '</option>';
    }
    echo '
	        
                                </select>
                              </div>';
    echo '

                    
                <input type="hidden" value="1" name="edit_usuario">
                </form>

        </div>
        <div class="modal-footer">
            <button form="edit_form_usuario" type="submit" class="btn btn-primary">Cadastrar</button>
        </div>
    </div>

	    

										
						              ';
  }
}
