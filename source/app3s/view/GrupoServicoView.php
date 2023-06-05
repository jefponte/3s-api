<?php
            
/**
 * Classe de visao para GrupoServico
 * @author Jefferson Uchôa Ponte <j.pontee@gmail.com>
 *
 */

namespace app3s\view;
use app3s\model\GrupoServico;


class GrupoServicoView {
    public function showInsertForm() {
		echo '
<!-- Button trigger modal -->
<button type="button" class="btn btn-primary m-3" data-toggle="modal" data-target="#modalAddGrupoServico">
  Adicionar
</button>

<!-- Modal -->
<div class="modal fade" id="modalAddGrupoServico" tabindex="-1" role="dialog" aria-labelledby="labelAddGrupoServico" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="labelAddGrupoServico">Adicionar Grupo Servico</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
          <form id="insert_form_grupo_servico" class="user" method="post">
            <input type="hidden" name="enviar_grupo_servico" value="1">                



                                        <div class="form-group">
                                            <label for="nome">Nome</label>
                                            <input type="text" class="form-control"  name="nome" id="nome" placeholder="Nome">
                                        </div>

						              </form>


      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Fechar</button>
        <button form="insert_form_grupo_servico" type="submit" class="btn btn-primary">Cadastrar</button>
      </div>
    </div>
  </div>
</div>
            
            
			
';
	}



                                            
                                            
    public function showList($lista){
           echo '
                                            
                                            
                                            

          <div class="card">
                <div class="card-header">
                  Lista Grupo Servico
                </div>
                <div class="card-body">
                                            
                                            
		<div class="table-responsive">
			<table class="table table-bordered" id="dataTable" width="100%"
				cellspacing="0">
				<thead>
					<tr>
						<th>Id</th>
						<th>Nome</th>
                        <th>Actions</th>
					</tr>
				</thead>
				<tfoot>
					<tr>
                        <th>Id</th>
                        <th>Nome</th>
                        <th>Actions</th>
					</tr>
				</tfoot>
				<tbody>';
            
            foreach($lista as $element){
                echo '<tr>';
                echo '<td>'.$element->getId().'</td>';
                echo '<td>'.$element->getNome().'</td>';
                echo '<td>
                        <a href="?page=grupo_servico&select='.$element->getId().'" class="btn btn-info text-white">Select</a>
                        <a href="?page=grupo_servico&edit='.$element->getId().'" class="btn btn-success text-white">Edit</a>
                        <a href="?page=grupo_servico&delete='.$element->getId().'" class="btn btn-danger text-white">Delete</a>
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
            

            
	public function showEditForm(GrupoServico $selecionado) {
		echo '
	    
	    

<div class="card o-hidden border-0 shadow-lg mb-4">
    <div class="card">
        <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
            <h6 class="m-0 font-weight-bold text-primary">Edit Grupo Servico</h6>
        </div>
        <div class="card-body">
            <form class="user" method="post" id="edit_form_grupo_servico">
                                        <div class="form-group">
                                            <label for="nome">Nome</label>
                                            <input type="text" class="form-control" value="'.$selecionado->getNome().'"  name="nome" id="nome" placeholder="Nome">
                						</div>
                <input type="hidden" value="1" name="edit_grupo_servico">
                </form>

        </div>
        <div class="modal-footer">
            <button form="edit_form_grupo_servico" type="submit" class="btn btn-primary">Cadastrar</button>
        </div>
    </div>
</div>

	    

										
						              ';
	}




            
        public function showSelected(GrupoServico $gruposervico){
            echo '
            
	<div class="card o-hidden border-0 shadow-lg">
        <div class="card">
            <div class="card-header">
                  Grupo Servico selecionado
            </div>
            <div class="card-body">
                Id: '.$gruposervico->getId().'<br>
                Nome: '.$gruposervico->getNome().'<br>
            
            </div>
        </div>
    </div>
            
            
';
    }


                                            
    public function confirmDelete(GrupoServico $grupoServico) {
		echo '
        
        
        
				<div class="card o-hidden border-0 shadow-lg">
					<div class="card-body p-0">
						<!-- Nested Row within Card Body -->
						<div class="row">
        
							<div class="col-lg-12">
								<div class="p-5">
									<div class="text-center">
										<h1 class="h4 text-gray-900 mb-4"> Delete Grupo Servico</h1>
									</div>
						              <form class="user" method="post">                    Are you sure you want to delete this object?

                                        <input type="submit" class="btn btn-primary btn-user btn-block" value="Delete" name="delete_grupo_servico">
                                        <hr>
                                            
						              </form>
                                            
								</div>
							</div>
						</div>
					</div>
                                            
                                            
                                            
                                            
	</div>';
	}
                      


}