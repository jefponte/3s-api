
$( ".change-level" ).click(function() {
    var dados = { nivel:  $(this).attr('nivel') };
    mudarNivel(dados);
});
function mudarNivel(dados){
    jQuery.ajax({
        type: "POST",
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        url: "?ajax=mudar_nivel",
        data: dados,
        success: function( data )
        {
            if(data.split(":")[1] == 'sucess'){
                window.location.href = './';
            }
        }
    });

}