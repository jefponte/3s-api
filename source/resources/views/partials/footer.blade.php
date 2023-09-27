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
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"
    integrity="sha384-Fy6S3B9q64WdZWQUiU+q4/2Lc9npb8tCaSX9FK7E8HnRr0Jz8D6OP9dO5Vg3Q9ct" crossorigin="anonymous">
</script>
<script src="js/popper.min.js"></script>
<script src="js/selectize.js"></script>
<script src="js/jquery.easyPaginate.js?a=1"></script>
<script src="js/ocorrencia.js?a=111"></script>
<script src="js/status_ocorrencia.js"></script>

@vite(['resources/js/app.js'])

</html>
