
$( ".change-level" ).click(function() {
    var dados = { nivel:  $(this).attr('nivel') };
    mudarNivel(dados);
});

function mudarNivel(dados){

    jQuery.ajax({
        type: "POST",
        url: "?ajax=mudar_nivel",
        data: dados,
        success: function( data )
        {



            if(data.split(":")[1] == 'sucess'){

                $("#botao-modal-resposta").click(function(){

                    window.location.href = './?demanda=1';

                });
                $("#textoModalResposta").text("Nível Alterado Com Sucesso! ");
                $("#modalResposta").modal("show");

            }
            else
            {

                $("#textoModalResposta").text("Falha ao tentar alterar nível! ");
                $("#modalResposta").modal("show");
            }


        }
    });


}