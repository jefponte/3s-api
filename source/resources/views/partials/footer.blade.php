</main>
<footer class="blog-footer">
    <p>Desenvolvido pela <a href="https://dti.unilab.edu.br/"> Diretoria de Tecnologia da Informação DTI - </a> / <a
            href="http://unilab.edu.br">Unilab</a></p>
    <p>versão do 3s: {{ env('APP_VERSION') }}</p>

</footer>



<!-- Modal -->
<div class="modal fade" id="modalResposta" tabindex="-1" role="dialog" aria-labelledby="labelModalResposta"
    aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="labelModalResposta">Resposta</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <span id="textoModalResposta"></span>
            </div>
            <div class="modal-footer">
                <button type="button" id="botao-modal-resposta" class="btn btn-primary"
                    data-dismiss="modal">Continuar</button>
            </div>
        </div>
    </div>
</div>


</body>
<script src="js/jquery-3.5.1.min.js"></script>
<script src="js/popper.min.js"></script>
<script src="js/datatables/jquery.dataTables.min.js"></script>
<script src="js/datatables/dataTables.bootstrap4.min.js"></script>

@vite(['resources/js/app.js'])


<script src="js/demo/datatables-demo.js"></script>
<script src="js/selectize.js"></script>
<script src="js/change-contraste.js?a=1"></script>
<script src="js/ocorrencia_selectize.js?a=1"></script>
<script src="js/jquery.easyPaginate.js?a=1"></script>
<script src="js/ocorrencia.js?a=1451"></script>

<script src="js/status_ocorrencia.js"></script>
</html>
